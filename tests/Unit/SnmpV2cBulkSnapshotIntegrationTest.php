<?php

namespace Tests\Unit;

use App\Models\NetworkSnmpProfile;
use App\NetworkMonitoring\NativeSnmpTransport;
use App\NetworkMonitoring\SnmpV2cBulkReader;
use Tests\TestCase;

class SnmpV2cBulkSnapshotIntegrationTest extends TestCase
{
    public function test_swos_bulk_response_is_converted_to_multiple_snapshot_rows(): void
    {
        config([
            'network_quality.snmp.v2c_enabled' => true,
            'network_quality.snmp.v2c_bulk_enabled' => true,
            'network_quality.snmp.timeout_us' => 1000000,
        ]);

        $reader = new class extends SnmpV2cBulkReader {
            public int $calls = 0;

            public function read(
                string $host,
                string $community,
                string $cursor,
                int $repetitions,
                int $timeoutUs
            ): array {
                $this->calls++;

                return [
                    'rows' => [
                        ['oid' => '1.3.6.1.2.1.1.1.0',
                            'type' => 4, 'value' => 'CSS326-24G-2S+ SwOS v2.18'],
                        ['oid' => '1.3.6.1.2.1.1.2.0',
                            'type' => 6, 'value' => '1.3.6.1.4.1.14988'],
                        ['oid' => '1.3.6.1.2.1.1.3.0',
                            'type' => 67, 'value' => '124324'],
                    ],
                    'finished' => true,
                ];
            }
        };

        $profile = new NetworkSnmpProfile();
        $profile->forceFill([
            'enabled' => true,
            'version' => '2c',
            'community' => 'readOnly-switch-2026',
        ]);

        $rows = (new NativeSnmpTransport($reader))->nextSnapshotSegment(
            $profile, '10.10.1.52', '1.3.6.1', '1.3.6.1', 30, 15, 8192
        );

        $this->assertSame(1, $reader->calls);
        $this->assertCount(3, $rows['rows']);
        $this->assertTrue($rows['finished']);
        $this->assertNull($rows['error']);
        $this->assertSame('1.3.6.1.2.1.1.3.0', $rows['cursor']);
        $this->assertSame('CSS326-24G-2S+ SwOS v2.18', $rows['rows'][0]['value']);
        $this->assertSame(67, $rows['rows'][2]['type']);
    }

    public function test_bulk_result_crossing_configured_root_is_not_saved(): void
    {
        config([
            'network_quality.snmp.v2c_enabled' => true,
            'network_quality.snmp.v2c_bulk_enabled' => true,
        ]);

        $reader = new class extends SnmpV2cBulkReader {
            public function read(
                string $host,
                string $community,
                string $cursor,
                int $repetitions,
                int $timeoutUs
            ): array {
                return [
                    'rows' => [
                        ['oid' => '1.3.6.1.2.1.1.1.0', 'type' => 4, 'value' => 'switch'],
                        ['oid' => '1.3.6.1.2.1.2.1.0', 'type' => 2, 'value' => '26'],
                    ],
                    'finished' => false,
                ];
            }
        };

        $profile = new NetworkSnmpProfile();
        $profile->forceFill([
            'version' => '2c',
            'enabled' => true,
            'community' => 'readOnly-switch-2026',
        ]);

        $result = (new NativeSnmpTransport($reader))->nextSnapshotSegment(
            $profile, '10.10.1.52',
            '1.3.6.1.2.1.1', '1.3.6.1.2.1.1', 30, 15, 8192
        );

        $this->assertCount(1, $result['rows']);
        $this->assertTrue($result['finished']);
        $this->assertSame('1.3.6.1.2.1.1.1.0', $result['cursor']);
    }
}
