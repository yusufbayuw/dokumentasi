<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceInterface;
use App\Models\IpAddress;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class IpUsageService
{
    public function assertAssignableToDevice(?int $ipAddressId, ?int $deviceId): void
    {
        if (! $ipAddressId) {
            return;
        }

        $usedByOtherDevice = Device::query()
            ->where('ip_address_id', $ipAddressId)
            ->when($deviceId, fn ($query) => $query->where('id', '!=', $deviceId))
            ->exists();

        $usedByOtherInterfaceOwner = DeviceInterface::query()
            ->where('ip_address_id', $ipAddressId)
            ->when($deviceId, fn ($query) => $query->where('device_id', '!=', $deviceId))
            ->exists();

        if ($usedByOtherDevice || $usedByOtherInterfaceOwner) {
            throw ValidationException::withMessages([
                'ip_address_id' => 'IP address sudah digunakan oleh perangkat lain.',
            ]);
        }
    }

    public function assertAssignableToInterface(?int $ipAddressId, int $deviceId, ?int $interfaceId = null): void
    {
        if (! $ipAddressId) {
            return;
        }

        $usedByOtherDevice = Device::query()
            ->where('ip_address_id', $ipAddressId)
            ->where('id', '!=', $deviceId)
            ->exists();

        $usedByOtherInterface = DeviceInterface::query()
            ->where('ip_address_id', $ipAddressId)
            ->when($interfaceId, fn ($query) => $query->where('id', '!=', $interfaceId))
            ->exists();

        if ($usedByOtherDevice || $usedByOtherInterface) {
            throw ValidationException::withMessages([
                'ip_address_id' => 'IP address sudah digunakan oleh perangkat atau interface lain.',
            ]);
        }
    }

    public function availableForDevice(?int $deviceId = null): array
    {
        $allowedIds = collect();

        if ($deviceId) {
            $allowedIds = $allowedIds
                ->merge(Device::query()->whereKey($deviceId)->pluck('ip_address_id'))
                ->merge(DeviceInterface::query()->where('device_id', $deviceId)->pluck('ip_address_id'))
                ->filter();
        }

        return IpAddress::query()
            ->where(function ($query) use ($allowedIds): void {
                $query->where('booked', false);

                if ($allowedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $allowedIds);
                }
            })
            ->orderByRaw('INET_ATON(nama)')
            ->pluck('nama', 'id')
            ->all();
    }

    public function availableForInterface(int $deviceId, ?int $interfaceId = null): array
    {
        $allowedIds = Device::query()
            ->whereKey($deviceId)
            ->pluck('ip_address_id')
            ->filter();

        if ($interfaceId) {
            $allowedIds = $allowedIds
                ->merge(DeviceInterface::query()->whereKey($interfaceId)->pluck('ip_address_id'))
                ->filter();
        }

        return IpAddress::query()
            ->where(function ($query) use ($allowedIds): void {
                $query->where('booked', false);

                if ($allowedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $allowedIds);
                }
            })
            ->orderByRaw('INET_ATON(nama)')
            ->pluck('nama', 'id')
            ->all();
    }

    public function syncBookedState(?int $ipAddressId): void
    {
        if (! $ipAddressId) {
            return;
        }

        $isUsed = Device::query()
            ->where('ip_address_id', $ipAddressId)
            ->exists()
            || DeviceInterface::query()
                ->where('ip_address_id', $ipAddressId)
                ->exists();

        IpAddress::query()
            ->whereKey($ipAddressId)
            ->update(['booked' => $isUsed]);
    }

    public function usedIpIds(): Collection
    {
        return Device::query()
            ->whereNotNull('ip_address_id')
            ->pluck('ip_address_id')
            ->merge(
                DeviceInterface::query()
                    ->whereNotNull('ip_address_id')
                    ->pluck('ip_address_id')
            )
            ->filter()
            ->unique()
            ->values();
    }
}
