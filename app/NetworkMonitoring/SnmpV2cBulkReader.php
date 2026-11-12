<?php

namespace App\NetworkMonitoring;

use RuntimeException;

/**
 * Bounded SNMPv2c GETBULK packet reader.
 *
 * PHP's native SNMP class offers GETNEXT and an unbounded WALK, but not a
 * bounded GETBULK. This read-only adapter encodes a single GETBULK PDU
 * directly, accepting at most 12 varbinds per response. No shell commands,
 * argv secrets, SNMP SETs, redirects or arbitrary hosts.
 *
 * Community strings are not encrypted on the wire in SNMPv2c.
 */
class SnmpV2cBulkReader
{
    /** @return array{rows:list<array{oid:string,value:string,type:int}>,finished:bool} */
    public function read(string $host, string $community, string $cursor, int $repetitions, int $timeoutUs): array
    {
        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! app(TargetGuard::class)->allows('icmp', $host)) {
            throw new RuntimeException('snmp_bulk_host_not_allowed');
        }

        $cursor = SnmpOidCursor::normalize($cursor);
        if ($cursor === null || ! SnmpOidCursor::within($cursor, '1.3.6.1')) {
            throw new RuntimeException('snmp_bulk_invalid_cursor');
        }

        $repetitions = max(1, min(12, $repetitions));
        $timeoutUs = max(200000, min(2000000, $timeoutUs));
        $requestId = random_int(1, 0x7fffffff);
        $packet = $this->request($requestId, $community, $cursor, $repetitions);

        $stream = @stream_socket_client(
            'udp://'.$host.':161',
            $errno,
            $errstr,
            $timeoutUs / 1000000,
            STREAM_CLIENT_CONNECT,
        );

        if ($stream === false) {
            throw new RuntimeException('snmp_bulk_socket_failed');
        }

        try {
            stream_set_timeout($stream, intdiv($timeoutUs, 1000000), $timeoutUs % 1000000);
            if (@fwrite($stream, $packet) !== strlen($packet)) {
                throw new RuntimeException('snmp_bulk_send_failed');
            }

            $response = @fread($stream, 65535);
            if (! is_string($response) || $response === '') {
                throw new RuntimeException('snmp_bulk_timeout');
            }

            return $this->parseResponse($response, $requestId, $community, $repetitions);
        } finally {
            fclose($stream);
        }
    }

    public function request(int $requestId, string $community, string $cursor, int $repetitions): string
    {
        if (strlen($community) > 128 || $requestId < 1 || $requestId > 0x7fffffff
            || ($cursor = SnmpOidCursor::normalize($cursor)) === null) {
            throw new RuntimeException('snmp_bulk_invalid_request');
        }

        $repetitions = max(1, min(12, $repetitions));
        $varbind = self::tlv(0x30, self::tlv(0x06, self::encodeOid($cursor))."\x05\x00");
        $pdu = self::tlv(0xa5,
            self::integer($requestId).self::integer(0).self::integer($repetitions).
            self::tlv(0x30, $varbind)
        );

        return self::tlv(0x30, self::integer(1).self::tlv(0x04, $community).$pdu);
    }

    /**
     * Parse one response packet, checking request ID and community, OID
     * boundaries and response size. No untrusted BER is eval'd or logged.
     *
     * @return array{rows:list<array{oid:string,value:string,type:int}>,finished:bool}
     */
    public function parseResponse(
        string $packet,
        int $requestId,
        string $community,
        int $maxRows = 12,
    ): array {
        if ($packet === '' || strlen($packet) > 65535) {
            throw new RuntimeException('snmp_bulk_packet_size');
        }

        $offset = 0;
        $message = self::readTlv($packet, $offset, 0x30);
        if ($offset !== strlen($packet)) {
            throw new RuntimeException('snmp_bulk_trailing_packet');
        }

        $offset = 0;
        $version = self::readTlv($message, $offset, 0x02);
        $returnedCommunity = self::readTlv($message, $offset, 0x04);
        $pdu = self::readTlv($message, $offset, 0xa2);
        if ($offset !== strlen($message) || self::decodeUnsigned($version) !== '1'
            || ! hash_equals($community, $returnedCommunity)) {
            throw new RuntimeException('snmp_bulk_invalid_envelope');
        }

        $offset = 0;
        $receivedId = self::readTlv($pdu, $offset, 0x02);
        $errorStatus = self::readTlv($pdu, $offset, 0x02);
        self::readTlv($pdu, $offset, 0x02); // error-index
        $varbinds = self::readTlv($pdu, $offset, 0x30);
        if ($offset !== strlen($pdu)
            || self::decodeUnsigned($receivedId) !== (string) $requestId) {
            throw new RuntimeException('snmp_bulk_wrong_request_id');
        }
        if (self::decodeUnsigned($errorStatus) !== '0') {
            throw new RuntimeException('snmp_bulk_agent_error');
        }

        $rows = [];
        $offset = 0;
        $finished = false;
        $maxRows = max(1, min(12, $maxRows));

        while ($offset < strlen($varbinds)) {
            if (count($rows) >= $maxRows) {
                throw new RuntimeException('snmp_bulk_too_many_varbinds');
            }

            $varbind = self::readTlv($varbinds, $offset, 0x30);
            $inner = 0;
            $oid = self::decodeOid(self::readTlv($varbind, $inner, 0x06));
            [$tag, $value] = self::readAnyTlv($varbind, $inner);
            if ($inner !== strlen($varbind)) {
                throw new RuntimeException('snmp_bulk_varbind_format');
            }

            if ($tag === 0x82) {
                $finished = true;
                break;
            }

            if ($tag === 0x80 || $tag === 0x81) {
                throw new RuntimeException('snmp_bulk_unavailable_oid');
            }

            $rows[] = [
                'oid' => $oid,
                'type' => $tag,
                'value' => self::decodeValue($tag, $value),
            ];
        }

        return ['rows' => $rows, 'finished' => $finished];
    }

    private static function decodeValue(int $tag, string $value): string
    {
        if (in_array($tag, [0x02, 0x41, 0x42, 0x43, 0x46], true)) {
            if ($tag === 0x02 && $value !== '' && (ord($value[0]) & 0x80)) {
                $bytes = '';
                foreach (str_split($value) as $byte) {
                    $bytes .= chr(ord($byte) ^ 0xff);
                }
                for ($i = strlen($bytes) - 1; $i >= 0; $i--) {
                    $next = (ord($bytes[$i]) + 1) & 0xff;
                    $bytes[$i] = chr($next);
                    if ($next !== 0) {
                        break;
                    }
                }

                return '-'.self::decodeUnsigned($bytes);
            }

            return self::decodeUnsigned($value);
        }

        if ($tag === 0x06) {
            return self::decodeOid($value);
        }

        if ($tag === 0x40 && strlen($value) === 4) {
            return implode('.', array_map('ord', str_split($value)));
        }

        if ($tag === 0x05) {
            return '';
        }

        // Preserve octet strings/opaque/vendor types as bytes; caller selects
        // UTF-8 or base64 and encrypts the resulting value at rest.
        return $value;
    }

    private static function decodeUnsigned(string $bytes): string
    {
        $digits = '0';
        foreach (str_split($bytes) as $byte) {
            $carry = ord($byte);
            for ($i = strlen($digits) - 1; $i >= 0; $i--) {
                $num = (ord($digits[$i]) - 48) * 256 + $carry;
                $digits[$i] = chr(48 + ($num % 10));
                $carry = intdiv($num, 10);
            }
            while ($carry > 0) {
                $digits = chr(48 + ($carry % 10)).$digits;
                $carry = intdiv($carry, 10);
            }
        }

        return ltrim($digits, '0') ?: '0';
    }

    private static function integer(int $value): string
    {
        if ($value < 0 || $value > 0x7fffffff) {
            throw new RuntimeException('snmp_bulk_invalid_integer');
        }

        $bytes = ltrim(pack('N', $value), "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return self::tlv(0x02, $bytes);
    }

    private static function encodeOid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        if (count($parts) < 2 || $parts[0] > 2
            || ($parts[0] < 2 && $parts[1] >= 40)) {
            throw new RuntimeException('snmp_bulk_invalid_oid');
        }

        $encoded = self::encodeBase128($parts[0] * 40 + $parts[1]);
        foreach (array_slice($parts, 2) as $part) {
            $encoded .= self::encodeBase128($part);
        }

        return $encoded;
    }

    private static function encodeBase128(int $n): string
    {
        $bytes = [($n & 0x7f)];
        $n >>= 7;
        while ($n > 0) {
            array_unshift($bytes, ($n & 0x7f) | 0x80);
            $n >>= 7;
        }

        return implode('', array_map('chr', $bytes));
    }

    private static function decodeOid(string $bytes): string
    {
        if ($bytes === '') {
            throw new RuntimeException('snmp_bulk_empty_oid');
        }

        $index = 0;
        $first = self::readBase128($bytes, $index);
        $arcs = $first < 40 ? [0, $first] :
            ($first < 80 ? [1, $first - 40] : [2, $first - 80]);

        while ($index < strlen($bytes)) {
            $arcs[] = self::readBase128($bytes, $index);
            if (count($arcs) > 128) {
                throw new RuntimeException('snmp_bulk_too_many_arcs');
            }
        }

        $oid = implode('.', $arcs);
        if (SnmpOidCursor::normalize($oid) === null) {
            throw new RuntimeException('snmp_bulk_invalid_oid');
        }

        return $oid;
    }

    private static function readBase128(string $bytes, int &$index): int
    {
        $value = 0;
        for ($i = 0; $i < 6; $i++) {
            if ($index >= strlen($bytes)) {
                throw new RuntimeException('snmp_bulk_bad_base128');
            }
            $byte = ord($bytes[$index++]);
            $value = $value * 128 + ($byte & 0x7f);
            if ($value > 4294967295) {
                throw new RuntimeException('snmp_bulk_oid_arc_overflow');
            }
            if (! ($byte & 0x80)) {
                return $value;
            }
        }

        throw new RuntimeException('snmp_bulk_bad_base128');
    }

    private static function tlv(int $tag, string $value): string
    {
        $len = strlen($value);
        if ($len < 128) {
            $encodedLength = chr($len);
        } else {
            $encodedLength = ltrim(pack('N', $len), "\x00");
            $encodedLength = chr(0x80 | strlen($encodedLength)).$encodedLength;
        }

        return chr($tag).$encodedLength.$value;
    }

    private static function readTlv(string $data, int &$offset, int $tag): string
    {
        [$actual, $value] = self::readAnyTlv($data, $offset);
        if ($actual !== $tag) {
            throw new RuntimeException('snmp_bulk_invalid_tag');
        }

        return $value;
    }

    /** @return array{int,string} */
    private static function readAnyTlv(string $data, int &$offset): array
    {
        $size = strlen($data);
        if ($offset + 2 > $size) {
            throw new RuntimeException('snmp_bulk_truncated_tlv');
        }

        $tag = ord($data[$offset++]);
        $length = ord($data[$offset++]);
        if ($length & 0x80) {
            $count = $length & 0x7f;
            if ($count < 1 || $count > 4 || $offset + $count > $size) {
                throw new RuntimeException('snmp_bulk_invalid_length');
            }

            $length = 0;
            for ($i = 0; $i < $count; $i++) {
                $length = $length * 256 + ord($data[$offset++]);
            }
        }

        if ($length > $size - $offset) {
            throw new RuntimeException('snmp_bulk_truncated_value');
        }

        $value = substr($data, $offset, $length);
        $offset += $length;

        return [$tag, $value];
    }
}
