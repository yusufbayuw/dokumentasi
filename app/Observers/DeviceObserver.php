<?php

namespace App\Observers;

use App\Models\Device;
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
    }

    public function updated(Device $device): void
    {
        if (! $device->wasChanged('ip_address_id')) {
            return;
        }

        $this->syncBookedState($device->getOriginal('ip_address_id'));
        $this->syncBookedState($device->ip_address_id);
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
