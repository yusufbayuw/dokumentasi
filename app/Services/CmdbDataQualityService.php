<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceConnection;
use App\Models\DeviceInterface;
use App\Models\DeviceMaintenance;
use App\Models\IpAddress;
use App\Models\Network;
use App\Support\Ipv4Cidr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CmdbDataQualityService
{
    public function issues(): array
    {
        $invalidIps = IpAddress::query()
            ->get(['id', 'nama'])
            ->filter(fn (IpAddress $ip): bool => filter_var($ip->nama, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
            ->values();

        $ipConflicts = $this->ipConflicts();
        $usedIds = app(IpUsageService::class)->usedIpIds()->flip();

        $bookedMismatches = IpAddress::query()
            ->get(['id', 'nama', 'booked'])
            ->filter(fn (IpAddress $ip): bool => (bool) $ip->booked !== $usedIds->has($ip->id))
            ->values();

        $monitoringWithoutIp = Device::query()
            ->where('monitoring_enabled', true)
            ->whereNull('ip_address_id')
            ->whereDoesntHave('interfaces', fn ($query) => $query
                ->where('is_management', true)
                ->whereNotNull('ip_address_id'))
            ->get(['id', 'nama']);

        $invalidDeviceMac = Device::query()
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->get(['id', 'nama', 'mac_address'])
            ->filter(fn (Device $device): bool => ! $this->validMac($device->mac_address));

        $invalidInterfaceMac = DeviceInterface::query()
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->get(['id', 'device_id', 'name', 'mac_address'])
            ->filter(fn (DeviceInterface $interface): bool => ! $this->validMac($interface->mac_address));

        $interfaceMismatch = DeviceConnection::query()
            ->with(['sourceInterface:id,device_id,name', 'targetInterface:id,device_id,name'])
            ->get()
            ->filter(function (DeviceConnection $connection): bool {
                $badSource = $connection->sourceInterface
                    && (int) $connection->sourceInterface->device_id !== (int) $connection->source_device_id;
                $badTarget = $connection->targetInterface
                    && (int) $connection->targetInterface->device_id !== (int) $connection->target_device_id;

                return $badSource || $badTarget;
            });

        $networkOverlaps = $this->networkOverlaps();
        $outsideSubnet = $this->ipsOutsideSubnet();
        $duplicateConnections = $this->duplicateConnections();

        $staleMinutes = max(1, (int) config('cmdb.monitoring.stale_minutes', 15));
        $staleDevices = Device::query()
            ->where('monitoring_enabled', true)
            ->whereNotNull('last_checked_at')
            ->where('last_checked_at', '<', now()->subMinutes($staleMinutes))
            ->get(['id', 'nama', 'last_checked_at']);

        $neverChecked = Device::query()
            ->where('monitoring_enabled', true)
            ->whereNull('last_checked_at')
            ->get(['id', 'nama']);

        $legacyCredentials = Device::query()
            ->where(function ($query): void {
                $query->whereNotNull('username')
                    ->orWhereNotNull('password');
            })
            ->get(['id', 'nama']);

        $overdueMaintenance = DeviceMaintenance::query()
            ->with('device:id,nama')
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get(['id', 'device_id', 'next_due_at']);

        return [
            $this->issue('invalid_ip', 'IP address tidak valid', $invalidIps->count(), 'danger', 'Alamat IPv4 invalid harus dibersihkan sebelum dipakai monitoring.', $invalidIps->take(10)->map(fn ($ip) => "#{$ip->id} {$ip->nama}")->all()),
            $this->issue('duplicate_ip', 'Konflik assignment IP', $ipConflicts->count(), 'danger', 'Satu IP tidak boleh dimiliki endpoint yang berbeda. Mirror legacy + management interface pada device yang sama tetap diperbolehkan.', $ipConflicts->take(10)->map(fn ($row) => $row['example'])->all()),
            $this->issue('booked_mismatch', 'Status IP tidak konsisten', $bookedMismatches->count(), 'danger', 'Flag booked harus mengikuti pemakaian pada Device maupun DeviceInterface.', $bookedMismatches->take(10)->map(fn ($ip) => "#{$ip->id} {$ip->nama} booked=" . ($ip->booked ? 'true' : 'false'))->all()),
            $this->issue('ip_outside_subnet', 'IP berada di luar subnet induk', $outsideSubnet->count(), 'danger', 'Alamat IP harus berada dalam host range network induknya.', $outsideSubnet->take(10)->all()),
            $this->issue('network_overlap', 'Subnet saling overlap', $networkOverlaps->count(), 'danger', 'Subnet overlap membuat IPAM ambigu dan harus diselesaikan.', $networkOverlaps->take(10)->all()),
            $this->issue('connection_interface_mismatch', 'Interface koneksi tidak sesuai perangkat', $interfaceMismatch->count(), 'danger', 'Source/target interface harus dimiliki oleh source/target device yang sama.', $interfaceMismatch->take(10)->map(fn ($c) => "Connection #{$c->id} {$c->source_device_id} → {$c->target_device_id}")->all()),
            $this->issue('self_connection', 'Koneksi perangkat ke dirinya sendiri', DeviceConnection::query()->whereColumn('source_device_id', 'target_device_id')->count(), 'danger', 'Self-loop biasanya merupakan kesalahan input untuk koneksi infrastruktur.', DeviceConnection::query()->whereColumn('source_device_id', 'target_device_id')->limit(10)->pluck('id')->map(fn ($id) => "Connection #{$id}")->all()),
            $this->issue('duplicate_connection', 'Koneksi jaringan terduplikasi', $duplicateConnections->count(), 'warning', 'Edge yang identik membuat diagram dan analisis topology menjadi ambigu.', $duplicateConnections->take(10)->all()),
            $this->issue('legacy_plaintext_credentials', 'Kredensial legacy masih plaintext', $legacyCredentials->count(), 'danger', 'Jalankan dokumentasi:migrate-device-credentials setelah backup dan verifikasi APP_KEY.', $legacyCredentials->take(10)->map(fn ($d) => "#{$d->id} {$d->nama}")->all()),
            $this->issue('monitoring_without_ip', 'Monitoring aktif tanpa management IP', $monitoringWithoutIp->count(), 'warning', 'Monitoring ICMP membutuhkan legacy IP atau Interface Management dengan IP.', $monitoringWithoutIp->take(10)->map(fn ($d) => "#{$d->id} {$d->nama}")->all()),
            $this->issue('monitoring_stale', 'Monitoring stale', $staleDevices->count(), 'warning', "Perangkat tidak diperiksa lebih dari {$staleMinutes} menit.", $staleDevices->take(10)->map(fn ($d) => "#{$d->id} {$d->nama} terakhir {$d->last_checked_at}")->all()),
            $this->issue('monitoring_never_checked', 'Monitoring belum pernah berjalan', $neverChecked->count(), 'warning', 'Pastikan scheduler dan queue worker monitoring aktif.', $neverChecked->take(10)->map(fn ($d) => "#{$d->id} {$d->nama}")->all()),
            $this->issue('critical_monitoring_disabled', 'Perangkat kritis tanpa monitoring', Device::query()->where('criticality', 'critical')->where('monitoring_enabled', false)->count(), 'warning', 'Perangkat critical sebaiknya dimonitor kecuali ada alasan operasional.'),
            $this->issue('disposed_monitoring_active', 'Perangkat retired/disposed masih dimonitor', Device::query()->whereIn('lifecycle_status', ['retired', 'disposed'])->where('monitoring_enabled', true)->count(), 'warning', 'Lifecycle nonaktif seharusnya tidak menghasilkan monitoring noise.'),
            $this->issue('without_location', 'Perangkat tanpa lokasi', Device::query()->whereNull('ruangan_id')->count(), 'warning', 'Lokasi fisik diperlukan untuk penanganan insiden dan audit.'),
            $this->issue('without_owner', 'Perangkat tanpa unit pemilik', Device::query()->whereNull('owner_unit_id')->count(), 'warning', 'Ownership dibutuhkan untuk accountability dan lifecycle.'),
            $this->issue('invalid_mac', 'Format MAC address tidak valid', $invalidDeviceMac->count() + $invalidInterfaceMac->count(), 'warning', 'Normalisasi MAC membantu discovery dan korelasi interface.'),
            $this->issue('maintenance_overdue', 'Maintenance terlewat', $overdueMaintenance->count(), 'warning', 'Jadwal maintenance yang lewat perlu ditindaklanjuti.', $overdueMaintenance->take(10)->map(fn ($m) => ($m->device?->nama ?? "Device #{$m->device_id}") . " due {$m->next_due_at}")->all()),
            $this->issue('without_asset_code', 'Perangkat tanpa kode aset', Device::query()->where(fn ($q) => $q->whereNull('asset_code')->orWhere('asset_code', ''))->count(), 'info', 'Kode aset stabil memperkuat identitas CI.'),
            $this->issue('without_serial', 'Perangkat tanpa serial number', Device::query()->where(fn ($q) => $q->whereNull('serial_number')->orWhere('serial_number', ''))->count(), 'info', 'Serial number berguna untuk garansi dan replacement.'),
            $this->issue('without_interface', 'Perangkat tanpa interface terstruktur', Device::query()->whereDoesntHave('interfaces')->count(), 'info', 'Interface adalah fondasi topology port-aware dan IPAM canonical.'),
        ];
    }

    private function issue(
        string $key,
        string $label,
        int $count,
        string $severity,
        string $description,
        array $examples = [],
    ): array {
        return compact('key', 'label', 'count', 'severity', 'description', 'examples');
    }

    private function validMac(?string $mac): bool
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', (string) $mac) === 1;
    }

    private function ipConflicts(): Collection
    {
        $legacy = Device::query()
            ->whereNotNull('ip_address_id')
            ->get(['id', 'nama', 'ip_address_id'])
            ->map(fn (Device $d) => [
                'ip_id' => (int) $d->ip_address_id,
                'device_id' => (int) $d->id,
                'kind' => 'legacy',
                'label' => "Device #{$d->id} {$d->nama}",
            ]);

        $interfaces = DeviceInterface::query()
            ->with('device:id,nama')
            ->whereNotNull('ip_address_id')
            ->get(['id', 'device_id', 'name', 'ip_address_id'])
            ->map(fn (DeviceInterface $i) => [
                'ip_id' => (int) $i->ip_address_id,
                'device_id' => (int) $i->device_id,
                'kind' => 'interface',
                'label' => "Interface #{$i->id} " . ($i->device?->nama ?? "Device #{$i->device_id}") . "/{$i->name}",
            ]);

        $ipNames = IpAddress::query()
            ->whereIn('id', $legacy->pluck('ip_id')->merge($interfaces->pluck('ip_id'))->unique())
            ->pluck('nama', 'id');

        return $legacy
            ->concat($interfaces)
            ->groupBy('ip_id')
            ->map(function (Collection $entries, int $ipId) use ($ipNames): ?array {
                $deviceCount = $entries->pluck('device_id')->unique()->count();
                $interfaceCount = $entries->where('kind', 'interface')->count();

                if ($deviceCount <= 1 && $interfaceCount <= 1) {
                    return null;
                }

                return [
                    'ip_id' => $ipId,
                    'example' => ($ipNames[$ipId] ?? "IP #{$ipId}") . ': ' . $entries->pluck('label')->implode(', '),
                ];
            })
            ->filter()
            ->values();
    }

    private function duplicateConnections(): Collection
    {
        return DeviceConnection::query()
            ->get([
                'id',
                'source_device_id',
                'source_interface_id',
                'target_device_id',
                'target_interface_id',
                'source_port',
                'target_port',
            ])
            ->groupBy(fn (DeviceConnection $connection): string => implode(':', [
                $connection->source_device_id,
                $connection->source_interface_id ?: '-',
                $connection->target_device_id,
                $connection->target_interface_id ?: '-',
                $connection->source_port ?: '-',
                $connection->target_port ?: '-',
            ]))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): string => 'Connection #' . $group->pluck('id')->implode(', #'))
            ->values();
    }

    private function networkOverlaps(): Collection
    {
        $networks = Network::query()->orderBy('id')->get(['id', 'address', 'netmask_original']);
        $issues = collect();

        for ($i = 0; $i < $networks->count(); $i++) {
            for ($j = $i + 1; $j < $networks->count(); $j++) {
                try {
                    $a = Ipv4Cidr::from((string) $networks[$i]->address, (int) $networks[$i]->netmask_original);
                    $b = Ipv4Cidr::from((string) $networks[$j]->address, (int) $networks[$j]->netmask_original);
                } catch (InvalidArgumentException) {
                    continue;
                }

                if ($a->overlaps($b)) {
                    $issues->push("#{$networks[$i]->id} {$a->network}/{$a->prefix} overlap #{$networks[$j]->id} {$b->network}/{$b->prefix}");
                }
            }
        }

        return $issues;
    }

    private function ipsOutsideSubnet(): Collection
    {
        return IpAddress::query()
            ->with('network:id,address,netmask_original')
            ->get(['id', 'network_id', 'nama'])
            ->filter(function (IpAddress $ip): bool {
                if (! $ip->network) {
                    return true;
                }

                try {
                    return ! Ipv4Cidr::from(
                        (string) $ip->network->address,
                        (int) $ip->network->netmask_original,
                    )->contains((string) $ip->nama);
                } catch (InvalidArgumentException) {
                    return true;
                }
            })
            ->map(fn (IpAddress $ip) => "#{$ip->id} {$ip->nama} pada network #{$ip->network_id}")
            ->values();
    }
}
