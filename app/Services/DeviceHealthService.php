<?php

namespace App\Services;

use App\Models\Device;

class DeviceHealthService
{
    public function status(Device $device): string
    {
        if (! $device->monitoring_enabled) {
            return 'disabled';
        }

        if (! $device->ip_address_id) {
            return 'unknown';
        }

        if (! $device->last_checked_at) {
            return 'unknown';
        }

        $staleMinutes = max(1, (int) config('cmdb.monitoring.stale_minutes', 15));

        if ($device->last_checked_at->lt(now()->subMinutes($staleMinutes))) {
            return 'stale';
        }

        return in_array($device->monitoring_status, ['online', 'offline'], true)
            ? $device->monitoring_status
            : 'unknown';
    }

    public function label(string $status): string
    {
        return match ($status) {
            'online' => 'Online',
            'offline' => 'Offline',
            'stale' => 'Stale',
            'disabled' => 'Nonaktif',
            default => 'Tidak diketahui',
        };
    }
}
