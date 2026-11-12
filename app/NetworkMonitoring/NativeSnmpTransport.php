<?php

namespace App\NetworkMonitoring;

use App\Models\NetworkSnmpProfile;
use RuntimeException;
use SNMP;

/**
 * Uses the native PHP SNMP extension, never invoking shell commands.
 * SNMPv3 authPriv remains the default. SNMPv2c is explicitly opt-in and
 * must only be used on tightly controlled management networks.
 * Credentials are decrypted in memory by encrypted Eloquent casts and are
 * never passed as command-line arguments or stored in measurement records.
 */
class NativeSnmpTransport
{
    public function available(): bool
    {
        return class_exists(SNMP::class);
    }

    /** @param list<string> $oids
     *  @return array<string, string>
     */
    public function read(NetworkSnmpProfile $profile, string $host, array $oids): array
    {
        if ($oids === []) {
            return [];
        }

        $session = $this->session($profile, $host);
        try {
            $response = @$session->get($oids, true);
            if (! is_array($response)) {
                throw new RuntimeException('snmp_read_failed');
            }

            $normalized = [];
            foreach ($response as $oid => $value) {
                $normalized[ltrim((string) $oid, '.')] = trim((string) $value, '" ');
            }

            return $normalized;
        } finally {
            $session->close();
        }
    }

    /** @return array<int, string> ifIndex => ifName */
    public function interfaceNames(NetworkSnmpProfile $profile, string $host): array
    {
        $session = $this->session($profile, $host);
        try {
            // Prefer ifName. Legacy SNMPv2c devices may only expose
            // IF-MIB::ifDescr, so fall back without changing CMDB data.
            $oids = [
                '.1.3.6.1.2.1.31.1.1.1.1', // ifName
                '.1.3.6.1.2.1.2.2.1.2', // ifDescr
            ];
            foreach ($oids as $oid) {
                try {
                    $values = @$session->walk($oid, true, 16);
                } catch (\Throwable) {
                    $values = false;
                }

                if (! is_array($values) || $values === []) {
                    continue;
                }

                $names = [];
                foreach ($values as $suffix => $value) {
                    $index = ltrim((string) $suffix, '.');
                    $name = trim((string) $value, '" ');

                    if (ctype_digit($index) && (int) $index > 0
                        && (int) $index <= 10000000 && $name !== '') {
                        $names[(int) $index] = substr($name, 0, 255);
                    }

                    // Hard cap to avoid copying unusually large SNMP tables.
                    if (count($names) >= 256) {
                        break;
                    }
                }

                if ($names !== []) {
                    return $names;
                }
            }

            return [];
        } finally {
            $session->close();
        }
    }

    /**
     * Bounded GETNEXT page for an SNMP OID snapshot.
     *
     * @return array{rows:list<array{oid:string,value:string,type:?int,encoding:string,raw_bytes:int,truncated:bool}>,cursor:string,finished:bool,error:?string}
     */
    public function nextSnapshotSegment(
        NetworkSnmpProfile $profile,
        string $host,
        string $root,
        string $cursor,
        int $limit,
        int $maxSeconds,
        int $maxValueBytes,
    ): array {
        $root = SnmpOidCursor::normalize($root);
        $cursor = SnmpOidCursor::normalize($cursor);
        if ($root === null || $cursor === null
            || ! SnmpOidCursor::within($cursor, $root)
            || ! SnmpOidCursor::within($root, '1.3.6.1')) {
            throw new RuntimeException('snmp_invalid_oid_root');
        }

        $limit = max(1, min(100, $limit));
        $maxSeconds = max(3, min(30, $maxSeconds));
        $maxValueBytes = max(512, min(65536, $maxValueBytes));

        $session = $this->session($profile, $host);
        try {
            $session->valueretrieval = SNMP_VALUE_OBJECT;
            $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
            $rows = [];
            $deadline = microtime(true) + $maxSeconds;

            while (count($rows) < $limit && microtime(true) < $deadline) {
                try {
                    // Array argument returns the NEXT numeric OID as a key.
                    // Unlike walk(), this keeps memory bounded for large MIBs.
                    $reply = @$session->getnext(['.'.$cursor]);
                } catch (\Throwable) {
                    return compact('rows', 'cursor') + [
                        'finished' => false, 'error' => 'snmp_getnext_failed',
                    ];
                }

                if (! is_array($reply) || count($reply) !== 1) {
                    return compact('rows', 'cursor') + [
                        'finished' => false, 'error' => 'snmp_getnext_failed',
                    ];
                }

                $oid = SnmpOidCursor::normalize((string) array_key_first($reply));
                if ($oid === null || ! SnmpOidCursor::greaterThan($oid, $cursor)) {
                    return compact('rows', 'cursor') + [
                        'finished' => false, 'error' => 'snmp_invalid_oid_sequence',
                    ];
                }

                if (! SnmpOidCursor::within($oid, $root)) {
                    return compact('rows', 'cursor') + [
                        'finished' => true, 'error' => null,
                    ];
                }

                $raw = reset($reply);
                $value = is_object($raw) && property_exists($raw, 'value')
                    ? (string) $raw->value : (string) $raw;
                $type = is_object($raw) && property_exists($raw, 'type')
                    ? (int) $raw->type : null;

                $rawBytes = strlen($value);
                $truncated = $rawBytes > $maxValueBytes;
                if ($truncated) {
                    $value = substr($value, 0, $maxValueBytes);
                }

                $encoding = preg_match('//u', $value) === 1 ? 'utf8' : 'base64';

                $rows[] = [
                    'oid' => $oid,
                    'value' => $encoding === 'base64' ? base64_encode($value) : $value,
                    'type' => $type,
                    'encoding' => $encoding,
                    'raw_bytes' => $rawBytes,
                    'truncated' => $truncated,
                ];
                $cursor = $oid;
            }

            return compact('rows', 'cursor') + [
                'finished' => false, 'error' => null,
            ];
        } finally {
            $session->close();
        }
    }

    private function session(NetworkSnmpProfile $profile, string $host): SNMP
    {
        if (! $this->available()) {
            throw new RuntimeException('snmp_extension_missing');
        }

        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! app(TargetGuard::class)->allows('icmp', $host)) {
            throw new RuntimeException('snmp_host_not_allowed');
        }

        if (! SnmpProtocolPolicy::canPoll($profile)) {
            throw new RuntimeException('snmp_protocol_disabled_or_invalid');
        }

        $timeout = max(200000, min(2000000, (int) config('network_quality.snmp.timeout_us', 1000000)));

        if ($profile->version === '2c') {
            // PHP 8.1 native extension: SNMP::VERSION_2C (capital C).
            // Community never appears on a command line, URL, response or log.
            // v2c traffic is not encrypted; restrict devices to a management VLAN.
            $session = new SNMP(SNMP::VERSION_2C, $host, (string) $profile->community, $timeout, 0);
        } elseif ($profile->version === '3') {
            if (! in_array($profile->auth_protocol, ['SHA', 'SHA256', 'SHA512'], true)) {
                throw new RuntimeException('snmp_auth_protocol_not_allowed');
            }

            $session = new SNMP(SNMP::VERSION_3, $host, (string) $profile->security_name, $timeout, 0);

            try {
                if (! @$session->setSecurity(
                    'authPriv',
                    (string) $profile->auth_protocol,
                    (string) $profile->auth_passphrase,
                    'AES',
                    (string) $profile->privacy_passphrase
                )) {
                    throw new RuntimeException('snmp_security_setup_failed');
                }
            } catch (\Throwable $error) {
                $session->close();
                throw $error;
            }
        } else {
            throw new RuntimeException('snmp_version_not_supported');
        }

        try {
            $session->valueretrieval = SNMP_VALUE_PLAIN;
            $session->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;

            return $session;
        } catch (\Throwable $error) {
            $session->close();
            throw $error;
        }
    }
}
