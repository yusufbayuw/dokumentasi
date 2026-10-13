<?php

namespace Tests\Unit;

use App\Services\FingerprintService;
use PHPUnit\Framework\TestCase;

class FingerprintDataSafetyTest extends TestCase
{
    public function test_user_snapshot_never_contains_device_password_or_card(): void
    {
        $safe = FingerprintService::safeUser([
            'uid' => 12,
            'userid' => '1001',
            'name' => "Operator\x00",
            'role' => 0,
            'password' => '12345678',
            'cardno' => '00001234',
            'fingerprint' => 'raw-template',
        ]);

        $this->assertSame([
            'user_id' => '1001',
            'uid' => 12,
            'name' => 'Operator',
            'role' => 0,
        ], $safe);
        $this->assertArrayNotHasKey('password', $safe);
        $this->assertArrayNotHasKey('fingerprint', $safe);
    }

    public function test_attendance_deduplication_is_stable_and_checks_dates(): void
    {
        $input = [
            'uid' => 7, 'id' => '1001', 'timestamp' => '2026-10-13 08:00:00',
            'state' => 1, 'type' => 255,
        ];

        $first = FingerprintService::safeAttendance($input);
        $second = FingerprintService::safeAttendance($input);

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first['event_hash']));
        $this->assertNull(FingerprintService::safeAttendance(array_merge($input, [
            'timestamp' => '2026-02-30 08:00:00',
        ])));
    }
}
