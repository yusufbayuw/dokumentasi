<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\DeviceHealthService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeviceHealthServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_disabled_monitoring_has_disabled_health(): void
    {
        $device = new Device();
        $device->forceFill(['monitoring_enabled' => false]);

        $this->assertSame('disabled', app(DeviceHealthService::class)->status($device));
    }

    public function test_device_without_management_ip_is_unknown(): void
    {
        $device = new Device();
        $device->forceFill([
            'monitoring_enabled' => true,
            'ip_address_id' => null,
        ]);

        $this->assertSame('unknown', app(DeviceHealthService::class)->status($device));
    }

    public function test_recent_online_and_offline_state_is_preserved(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        config(['cmdb.monitoring.stale_minutes' => 15]);

        $online = new Device();
        $online->forceFill([
            'monitoring_enabled' => true,
            'ip_address_id' => 1,
            'last_checked_at' => now()->subMinutes(2),
            'monitoring_status' => 'online',
        ]);

        $offline = new Device();
        $offline->forceFill([
            'monitoring_enabled' => true,
            'ip_address_id' => 2,
            'last_checked_at' => now()->subMinutes(2),
            'monitoring_status' => 'offline',
        ]);

        $service = app(DeviceHealthService::class);

        $this->assertSame('online', $service->status($online));
        $this->assertSame('offline', $service->status($offline));
    }

    public function test_old_monitoring_state_becomes_stale(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        config(['cmdb.monitoring.stale_minutes' => 15]);

        $device = new Device();
        $device->forceFill([
            'monitoring_enabled' => true,
            'ip_address_id' => 1,
            'last_checked_at' => now()->subMinutes(16),
            'monitoring_status' => 'online',
        ]);

        $this->assertSame('stale', app(DeviceHealthService::class)->status($device));
    }
}
