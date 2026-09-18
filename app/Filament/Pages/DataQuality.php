<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\IpAddress;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DataQuality extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Laporan & Audit';
    protected static ?string $navigationLabel = 'Kualitas Data';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Kualitas Data CMDB';
    protected static string $view = 'filament.pages.data-quality';

    public array $issues = [];

    public function mount(): void
    {
        $this->issues = $this->buildIssues();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    private function buildIssues(): array
    {
        $duplicateIpGroups = Device::query()
            ->whereNotNull('ip_address_id')
            ->select('ip_address_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ip_address_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $duplicateIpDevices = (int) $duplicateIpGroups->sum('total');

        $bookedWithoutDevice = IpAddress::query()
            ->where('booked', true)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('devices')
                    ->whereColumn('devices.ip_address_id', 'ip_addresses.id');
            })
            ->count();

        $usedButNotBooked = IpAddress::query()
            ->where('booked', false)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('devices')
                    ->whereColumn('devices.ip_address_id', 'ip_addresses.id');
            })
            ->count();

        $invalidMac = Device::query()
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->get(['id', 'mac_address'])
            ->filter(fn (Device $device): bool => preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', (string) $device->mac_address) !== 1)
            ->count();

        return [
            $this->issue('duplicate_ip', 'IP dipakai lebih dari satu perangkat', $duplicateIpDevices, 'danger', 'Berpotensi menimbulkan konflik jaringan. Periksa assignment IP sebelum monitoring atau discovery diaktifkan.'),
            $this->issue('booked_mismatch', 'Status IP tidak konsisten', $bookedWithoutDevice + $usedButNotBooked, 'danger', "{$bookedWithoutDevice} IP booked tanpa perangkat, {$usedButNotBooked} IP dipakai tetapi tidak booked."),
            $this->issue('without_ip', 'Perangkat tanpa IP', Device::query()->whereNull('ip_address_id')->count(), 'warning', 'Tidak selalu salah, tetapi perangkat jaringan/managed device sebaiknya memiliki alamat management.'),
            $this->issue('without_location', 'Perangkat tanpa lokasi', Device::query()->whereNull('ruangan_id')->count(), 'warning', 'Lokasi fisik diperlukan untuk drill-down Gedung/Lantai/Ruangan dan penanganan insiden.'),
            $this->issue('without_owner', 'Perangkat tanpa unit pemilik', Device::query()->whereNull('owner_unit_id')->count(), 'warning', 'Ownership dibutuhkan untuk accountability, lifecycle, dan laporan per unit.'),
            $this->issue('without_asset_code', 'Perangkat tanpa kode aset', Device::query()->where(function ($query): void {
                $query->whereNull('asset_code')->orWhere('asset_code', '');
            })->count(), 'info', 'Lengkapi bertahap untuk menjadikan CMDB sebagai referensi aset yang stabil.'),
            $this->issue('without_serial', 'Perangkat tanpa serial number', Device::query()->where(function ($query): void {
                $query->whereNull('serial_number')->orWhere('serial_number', '');
            })->count(), 'info', 'Berguna untuk garansi, vendor, audit aset, dan proses replacement.'),
            $this->issue('invalid_mac', 'Format MAC address tidak valid', $invalidMac, 'warning', 'Normalisasi MAC address membantu pencarian, discovery, dan korelasi interface.'),
            $this->issue('without_topology', 'Perangkat tanpa record topology legacy', Device::query()->whereDoesntHave('topology')->count(), 'info', 'Topology legacy masih menjadi compatibility layer selama transisi ke graph connection.'),
            $this->issue('without_interface', 'Perangkat tanpa interface terstruktur', Device::query()->whereDoesntHave('interfaces')->count(), 'info', 'Interface adalah fondasi untuk koneksi port-aware, VLAN, SNMP, dan IPAM tahap berikutnya.'),
        ];
    }

    private function issue(string $key, string $label, int $count, string $severity, string $description): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'severity' => $severity,
            'description' => $description,
            'device_url' => DeviceResource::getUrl('index'),
        ];
    }
}
