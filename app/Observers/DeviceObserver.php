<?php

namespace App\Observers;

use App\Models\Device;
use App\Models\DeviceMovement;
use App\Models\IpAddress;
use App\Models\Topology;

class DeviceObserver
{
    public function created(Device $device): void
    {
        $this->syncBookedState($device->ip_address_id);

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
            $this->syncBookedState($device->getOriginal('ip_address_id'));
            $this->syncBookedState($device->ip_address_id);
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
        $this->syncBookedState($device->getOriginal('ip_address_id'));
    }

    public function restored(Device $device): void
    {
        $this->syncBookedState($device->ip_address_id);

        Topology::firstOrCreate([
            'device_id' => $device->id,
        ]);
    }

    public function forceDeleted(Device $device): void
    {
        $this->syncBookedState($device->getOriginal('ip_address_id'));
    }

    private function syncBookedState(?int $ipAddressId): void
    {
        if (! $ipAddressId) {
            return;
        }

        $isUsed = Device::query()
            ->where('ip_address_id', $ipAddressId)
            ->exists();

        IpAddress::query()
            ->whereKey($ipAddressId)
            ->update(['booked' => $isUsed]);
    }
}
