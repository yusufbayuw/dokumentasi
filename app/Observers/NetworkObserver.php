<?php

namespace App\Observers;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Network;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkObserver
{
    private const MAX_MANAGED_HOSTS = 65534;

    public function created(Network $network): void
    {
        $this->syncIpPool($network);
    }

    public function updating(Network $network): void
    {
        if (! $network->isDirty(['hostMin', 'hostMax'])) {
            return;
        }

        [$startLong, $endLong] = $this->validatedRange($network);

        $assignedIpIds = Device::query()
            ->whereNotNull('ip_address_id')
            ->pluck('ip_address_id');

        if ($assignedIpIds->isEmpty()) {
            return;
        }

        $assignedIps = IpAddress::query()
            ->where('network_id', $network->id)
            ->whereIn('id', $assignedIpIds)
            ->get(['id', 'nama']);

        foreach ($assignedIps as $ipAddress) {
            $ipLong = ip2long($ipAddress->nama);

            if ($ipLong === false || $ipLong < $startLong || $ipLong > $endLong) {
                throw ValidationException::withMessages([
                    'address' => 'Perubahan jaringan dibatalkan karena ada IP yang masih dipakai perangkat dan akan keluar dari rentang jaringan baru.',
                ]);
            }
        }
    }

    public function updated(Network $network): void
    {
        if (! $network->wasChanged(['hostMin', 'hostMax'])) {
            return;
        }

        $this->syncIpPool($network);
    }

    public function deleted(Network $network): void
    {
        // Foreign key pada ip_addresses akan menangani penghapusan sesuai konfigurasi schema.
    }

    public function restored(Network $network): void
    {
        $this->syncIpPool($network);
    }

    public function forceDeleted(Network $network): void
    {
        //
    }

    private function syncIpPool(Network $network): void
    {
        [$startLong, $endLong] = $this->validatedRange($network);

        DB::transaction(function () use ($network, $startLong, $endLong): void {
            $existing = IpAddress::query()
                ->where('network_id', $network->id)
                ->get(['id', 'nama'])
                ->keyBy('nama');

            $desiredNames = [];
            $rows = [];
            $now = now();

            for ($ipLong = $startLong; $ipLong <= $endLong; $ipLong++) {
                $ip = long2ip($ipLong);
                $desiredNames[$ip] = true;

                if ($existing->has($ip)) {
                    continue;
                }

                $rows[] = [
                    'network_id' => $network->id,
                    'nama' => $ip,
                    'status' => false,
                    'booked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 1000) {
                    IpAddress::insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                IpAddress::insert($rows);
            }

            $obsoleteIds = $existing
                ->filter(fn (IpAddress $ipAddress): bool => ! isset($desiredNames[$ipAddress->nama]))
                ->pluck('id');

            if ($obsoleteIds->isEmpty()) {
                return;
            }

            $usedIds = Device::query()
                ->whereIn('ip_address_id', $obsoleteIds)
                ->pluck('ip_address_id')
                ->filter()
                ->unique();

            $deletableIds = $obsoleteIds->diff($usedIds);

            if ($deletableIds->isNotEmpty()) {
                IpAddress::query()->whereIn('id', $deletableIds)->delete();
            }
        });
    }

    private function validatedRange(Network $network): array
    {
        $startLong = ip2long((string) $network->hostMin);
        $endLong = ip2long((string) $network->hostMax);

        if ($startLong === false || $endLong === false || $startLong > $endLong) {
            throw ValidationException::withMessages([
                'address' => 'Rentang IP jaringan tidak valid.',
            ]);
        }

        $hostCount = ($endLong - $startLong) + 1;

        if ($hostCount > self::MAX_MANAGED_HOSTS) {
            throw ValidationException::withMessages([
                'netmask_original' => 'Rentang jaringan terlalu besar untuk dikelola. Maksimal 65.534 alamat host.',
            ]);
        }

        return [$startLong, $endLong];
    }
}
