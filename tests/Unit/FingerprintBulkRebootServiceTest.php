<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\TypeDevice;
use App\Models\User;
use App\Services\FingerprintBulkRebootService;
use PHPUnit\Framework\TestCase;

class FingerprintBulkRebootServiceTest extends TestCase
{
    private function device(string $typeName, ?string $ipv4): Device
    {
        $device = new Device();
        $type = new TypeDevice();
        $type->nama = $typeName;
        $device->setRelation('type', $type);

        if ($ipv4 !== null) {
            $ip = new IpAddress();
            $ip->nama = $ipv4;
            $device->setRelation('ip', $ip);
        } else {
            $device->setRelation('ip', null);
            $device->setRelation('managementInterface', null);
        }

        return $device;
    }

    public function test_only_fingerprint_devices_with_valid_management_ipv4_are_eligible(): void
    {
        $this->assertTrue(FingerprintBulkRebootService::isEligible(
            $this->device('Finger Print', '192.168.10.21'),
        ));
        $this->assertFalse(FingerprintBulkRebootService::isEligible(
            $this->device('Finger Print', null),
        ));
        $this->assertFalse(FingerprintBulkRebootService::isEligible(
            $this->device('Finger Print', 'invalid-ip'),
        ));
        $this->assertFalse(FingerprintBulkRebootService::isEligible(
            $this->device('Network Switch', '192.168.10.22'),
        ));
    }

    public function test_batch_schedule_is_staggered_by_two_seconds(): void
    {
        $this->assertSame(0, FingerprintBulkRebootService::delaySeconds(0));
        $this->assertSame(2, FingerprintBulkRebootService::delaySeconds(1));
        $this->assertSame(6, FingerprintBulkRebootService::delaySeconds(3));
    }

    public function test_only_super_admin_can_request_fleet_reboot(): void
    {
        $this->assertFalse(FingerprintBulkRebootService::canRequest(null));

        $user = $this->createMock(User::class);
        $user->method('hasRole')->with('super_admin')->willReturn(false);
        $this->assertFalse(FingerprintBulkRebootService::canRequest($user));

        $admin = $this->createMock(User::class);
        $admin->method('hasRole')->with('super_admin')->willReturn(true);
        $this->assertTrue(FingerprintBulkRebootService::canRequest($admin));
    }
}
