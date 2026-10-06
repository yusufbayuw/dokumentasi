<?php

namespace App\Observers;

use App\Models\DeviceInterface;
use App\Services\IpUsageService;

class DeviceInterfaceObserver
{
    public function saving(DeviceInterface $interface): void
    {
        app(IpUsageService::class)->assertAssignableToInterface(
            $interface->ip_address_id,
            (int) $interface->device_id,
            $interface->exists ? (int) $interface->getKey() : null,
        );
    }

    public function saved(DeviceInterface $interface): void
    {
        $service = app(IpUsageService::class);

        if ($interface->wasChanged('ip_address_id')) {
            $service->syncBookedState($interface->getOriginal('ip_address_id'));
        }

        $service->syncBookedState($interface->ip_address_id);
    }

    public function deleted(DeviceInterface $interface): void
    {
        app(IpUsageService::class)->syncBookedState($interface->getOriginal('ip_address_id'));
    }
}
