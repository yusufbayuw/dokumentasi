<?php

namespace App\Services;

use App\Models\DeviceConnection;
use App\Models\DeviceInterface;
use Illuminate\Validation\ValidationException;

class DeviceConnectionIntegrityService
{
    public function validate(DeviceConnection $connection): void
    {
        if ((int) $connection->source_device_id === (int) $connection->target_device_id) {
            throw ValidationException::withMessages([
                'target_device_id' => 'Perangkat sumber dan tujuan harus berbeda.',
            ]);
        }

        $this->assertInterfaceOwner(
            $connection->source_interface_id,
            (int) $connection->source_device_id,
            'source_interface_id',
        );

        $this->assertInterfaceOwner(
            $connection->target_interface_id,
            (int) $connection->target_device_id,
            'target_interface_id',
        );

        if (
            $connection->source_interface_id
            && $connection->target_interface_id
            && (int) $connection->source_interface_id === (int) $connection->target_interface_id
        ) {
            throw ValidationException::withMessages([
                'target_interface_id' => 'Interface sumber dan tujuan harus berbeda.',
            ]);
        }
    }

    private function assertInterfaceOwner(?int $interfaceId, int $deviceId, string $field): void
    {
        if (! $interfaceId) {
            return;
        }

        $valid = DeviceInterface::query()
            ->whereKey($interfaceId)
            ->where('device_id', $deviceId)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                $field => 'Interface yang dipilih tidak dimiliki oleh perangkat terkait.',
            ]);
        }
    }
}
