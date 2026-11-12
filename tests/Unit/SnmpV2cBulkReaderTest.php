<?php

namespace Tests\Unit;

use App\NetworkMonitoring\SnmpV2cBulkReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SnmpV2cBulkReaderTest extends TestCase
{
    private const COMMUNITY = 'readOnly-SwOS-Example';

    private function tlv(int $tag, string $content): string
    {
        $n = strlen($content);
        $length = $n < 128 ? chr($n) : "\x81".chr($n);
        return chr($tag).$length.$content;
    }

    private function integer(int $n): string
    {
        return $this->tlv(0x02, chr($n));
    }

    private function oid(string $suffix): string
    {
        // Numeric test OIDs under .1.3.6.1.2.1.1.*, all arcs < 128.
        return $this->tlv(0x06, "\x2b\x06\x01\x02\x01\x01".$suffix);
    }

    private function varbind(string $suffix, int $type, string $value): string
    {
        return $this->tlv(0x30, $this->oid($suffix).$this->tlv($type, $value));
    }

    private function response(
        string $varbinds,
        int $requestId = 29,
        string $community = self::COMMUNITY,
    ): string {
        return $this->tlv(0x30,
            $this->integer(1).
            $this->tlv(0x04, $community).
            $this->tlv(0xa2,
                $this->integer($requestId).
                $this->integer(0).
                $this->integer(0).
                $this->tlv(0x30, $varbinds)
            )
        );
    }

    public function test_decodes_successive_swos_varbinds_without_native_snmp(): void
    {
        $response = $this->response(
            $this->varbind("\x01\x00", 0x04, 'CSS326-24G-2S+ SwOS v2.18').
            $this->varbind("\x02\x00", 0x06, "\x2b\x06\x01\x04\x01\xef\x08")
        );

        $out = (new SnmpV2cBulkReader())->parseResponse(
            $response, 29, self::COMMUNITY, 8
        );

        $this->assertFalse($out['finished']);
        $this->assertSame([
            ['oid' => '1.3.6.1.2.1.1.1.0',
                'type' => 4, 'value' => 'CSS326-24G-2S+ SwOS v2.18'],
            ['oid' => '1.3.6.1.2.1.1.2.0',
                'type' => 6, 'value' => '1.3.6.1.4.1.14216'],
        ], $out['rows']);
    }

    public function test_recognizes_end_of_mib_not_as_agent_failure(): void
    {
        $out = (new SnmpV2cBulkReader())->parseResponse(
            $this->response($this->varbind("\x09\x00", 0x82, '')),
            29,
            self::COMMUNITY,
        );

        $this->assertTrue($out['finished']);
        $this->assertSame([], $out['rows']);
    }

    public function test_decodes_64_bit_counters_without_integer_overflow(): void
    {
        $out = (new SnmpV2cBulkReader())->parseResponse(
            $this->response(
                $this->varbind("\x03\x00", 0x46, "\x01\x00\x00\x00\x00")
            ), 29, self::COMMUNITY
        );

        $this->assertSame('4294967296', $out['rows'][0]['value']);
        $this->assertSame(70, $out['rows'][0]['type']);
    }

    public function test_rejects_wrong_request_id_and_wrong_community(): void
    {
        $reader = new SnmpV2cBulkReader();
        $response = $this->response($this->varbind("\x01\x00", 0x04, 'switch'));

        try {
            $reader->parseResponse($response, 30, self::COMMUNITY);
            $this->fail('Wrong request ID should be rejected');
        } catch (RuntimeException $error) {
            $this->assertSame('snmp_bulk_wrong_request_id', $error->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $reader->parseResponse($response, 29, 'different-community');
    }

    public function test_prepares_getbulk_not_set_and_repetition_count_is_bounded(): void
    {
        $reader = new SnmpV2cBulkReader();
        $request = $reader->request(29, self::COMMUNITY, '1.3.6.1.2.1.1.1.0', 99);

        $this->assertStringContainsString(self::COMMUNITY, $request);
        $this->assertStringContainsString("\xa5", $request);
        $this->assertStringContainsString("\x02\x01\x0c", $request);
        $this->assertStringContainsString("\x06\x08\x2b\x06\x01\x02\x01\x01\x01\x00", $request);
    }

    public function test_rejects_truncated_ber_response(): void
    {
        $this->expectException(RuntimeException::class);
        (new SnmpV2cBulkReader())->parseResponse("\x30\x82\x01", 29, self::COMMUNITY);
    }
}
