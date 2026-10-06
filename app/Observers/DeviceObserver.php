<?php

namespace App\Observers;

use App\Models\Device;
use App\Models\DeviceMovement;
use App\Models\Topology;
use App\Services\IpUsageService;

class DeviceObserver
{
    public function saving(Device $device): void
    {
        app(IpUsageService::class)->assertAssignableToDevice(
            $device->ip_address_id,
            $device->exists ? (int) $device->getKey() : null,
        );

        if (in_array($device->lifecycle_status, ['retired', 'disposed'], true)) {
            $device->monitoring_enabled = false;
            $device->monitoring_status = 'unknown';
            $device->consecutive_failures = 0;
        }
    }

    public function created(Device $device): void
    {
        app(IpUsageService::class)->syncBookedState($device->ip_address_id);

        Topology::firstOrCreate([
            'device_id' => $device->id,
        ]);

        if ($device->ruangan_id) {
            DeviceMovement::query()->create([
                'device_id' => $device->id,
                'from_ruangan_id' => null,
                'to_ruangan_id' => $device->ruangan_id,
                'moved_at' => now(),
                'movement_type' => 'initial_assignment',
                'automatic' => true,
                'changed_by' => auth()->id(),
            ]);
        }
    }

    public function updated(Device $device): void
    {
        if ($device->wasChanged('ip_address_id')) {
            $service = app(IpUsageService::class);
            $service->syncBookedState($device->getOriginal('ip_address_id'));
            $service->syncBookedState($device->ip_address_id);
        }

        if ($device->wasChanged('monitoring_enabled') && ! $device->monitoring_enabled) {
            $device->forceFill([
                'monitoring_status' => 'unknown',
                'consecutive_failures' => 0,
            ])->saveQuietly();
        }

        if ($device->wasChanged('ruangan_id')) {
            DeviceMovement::query()->create([
                'device_id' => $device->id,
                'from_ruangan_id' => $device->getOriginal('ruangan_id'),
                'to_ruangan_id' => $device->ruangan_id,
                'moved_at' => now(),
                'movement_type' => 'relocation',
                'automatic' => true,
                'changed_by' => auth()->id(),
            ]);
        }
    }

    public function deleted(Device $device): void
    {
        app(IpUsageService::class)->syncBookedState($device->getOriginal('ip_address_id'));
    }

    public function restored(Device $device): void
    {
        app(IpUsageService::class)->syncBookedState($device->ip_address_id);

        Topology::firstOrCreate([
            'device_id' => $device->id,
        ]);
    }

    public function forceDeleted(Device $device): void
    {
        app(IpUsageService::class)->syncBookedState($device->getOriginal('ip_address_id'));
    }
}
