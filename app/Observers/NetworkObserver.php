<?php

namespace App\Observers;

use App\Models\IpAddress;
use App\Models\Network;
use App\Services\IpUsageService;
use App\Support\Ipv4Cidr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class NetworkObserver
{
    private const MIN_PREFIX = 16;
    private const MAX_PREFIX = 30;
    private const MAX_MANAGED_HOSTS = 65534;

    public function saving(Network $network): void
    {
        $this->hydrateDerivedFields($network);
        $this->assertNoOverlap($network);
    }

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
        $usedIpIds = app(IpUsageService::class)->usedIpIds();

        if ($usedIpIds->isEmpty()) {
            return;
        }

        $assignedIps = IpAddress::query()
            ->where('network_id', $network->id)
            ->whereIn('id', $usedIpIds)
            ->get(['id', 'nama']);

        foreach ($assignedIps as $ipAddress) {
            $ipLong = ip2long($ipAddress->nama);

            if ($ipLong === false || $ipLong < $startLong || $ipLong > $endLong) {
                throw ValidationException::withMessages([
                    'address' => 'Perubahan jaringan dibatalkan karena ada IP yang masih dipakai dan akan keluar dari rentang jaringan baru.',
                ]);
            }
        }
    }

    public function updated(Network $network): void
    {
        if ($network->wasChanged(['hostMin', 'hostMax'])) {
            $this->syncIpPool($network);
        }
    }

    public function restored(Network $network): void
    {
        $this->syncIpPool($network);
    }

    private function hydrateDerivedFields(Network $network): void
    {
        $prefix = (int) $network->netmask_original;

        if ($prefix < self::MIN_PREFIX || $prefix > self::MAX_PREFIX) {
            throw ValidationException::withMessages([
                'netmask_original' => 'Prefix jaringan yang dikelola harus antara /16 dan /30.',
            ]);
        }

        try {
            $cidr = Ipv4Cidr::from((string) $network->address, $prefix);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'address' => 'Alamat IPv4 jaringan tidak valid.',
            ]);
        }

        if ($cidr->hostCount > self::MAX_MANAGED_HOSTS) {
            throw ValidationException::withMessages([
                'netmask_original' => 'Rentang jaringan terlalu besar untuk dikelola. Maksimal 65.534 alamat host.',
            ]);
        }

        $network->netmask = $cidr->netmask;
        $network->network = $cidr->network;
        $network->broadcast = $cidr->broadcast;
        $network->hostMin = $cidr->hostMin;
        $network->hostMax = $cidr->hostMax;
        $network->hostPerNet = (string) $cidr->hostCount;
    }

    private function assertNoOverlap(Network $network): void
    {
        $candidate = Ipv4Cidr::from((string) $network->address, (int) $network->netmask_original);

        Network::query()
            ->when($network->exists, fn ($query) => $query->where('id', '!=', $network->getKey()))
            ->get(['id', 'address', 'netmask_original'])
            ->each(function (Network $existing) use ($candidate): void {
                try {
                    $other = Ipv4Cidr::from((string) $existing->address, (int) $existing->netmask_original);
                } catch (InvalidArgumentException) {
                    return;
                }

                if ($candidate->overlaps($other)) {
                    throw ValidationException::withMessages([
                        'address' => "Subnet overlap dengan network #{$existing->id} ({$other->network}/{$other->prefix}).",
                    ]);
                }
            });
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

            $usedIds = app(IpUsageService::class)->usedIpIds();
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

        return [
            (int) sprintf('%u', $startLong),
            (int) sprintf('%u', $endLong),
        ];
    }
}
