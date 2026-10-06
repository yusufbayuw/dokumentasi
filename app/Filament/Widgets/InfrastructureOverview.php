<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use App\Models\DeviceMaintenance;
use App\Models\Network;
use App\Services\DeviceHealthService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InfrastructureOverview extends BaseWidget
{
    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    protected function getStats(): array
    {
        $devices = Device::query()
            ->with(['interfaces:id,device_id,ip_address_id,is_management'])
            ->get([
                'id',
                'ip_address_id',
                'monitoring_enabled',
                'monitoring_status',
                'last_checked_at',
                'criticality',
            ]);

        $health = app(DeviceHealthService::class);

        $total = $devices->count();
        $monitored = $devices->where('monitoring_enabled', true)->count();
        $online = $devices->filter(fn (Device $device): bool => $health->status($device) === 'online')->count();
        $offline = $devices->filter(fn (Device $device): bool => $health->status($device) === 'offline')->count();
        $stale = $devices->filter(fn (Device $device): bool => $health->status($device) === 'stale')->count();
        $unknown = $devices->filter(fn (Device $device): bool => $health->status($device) === 'unknown')->count();
        $withoutIp = $devices->filter(fn (Device $device): bool => ! $device->ip_address_id
            && ! $device->interfaces->contains(fn ($interface): bool => $interface->is_management && $interface->ip_address_id))
            ->count();
        $criticalOffline = $devices
            ->where('criticality', 'critical')
            ->filter(fn (Device $device): bool => $health->status($device) === 'offline')
            ->count();

        $warrantyExpiring = Device::query()
            ->whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [today(), today()->addDays(90)])
            ->count();

        $maintenanceOverdue = DeviceMaintenance::query()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        return [
            Stat::make('Total Perangkat', number_format($total))
                ->description(number_format($monitored) . ' monitoring aktif')
                ->icon('heroicon-o-computer-desktop'),
            Stat::make('Online', number_format($online))
                ->description("{$stale} stale · {$unknown} unknown")
                ->color('success')
                ->icon('heroicon-o-signal'),
            Stat::make('Offline', number_format($offline))
                ->description(number_format($withoutIp) . ' perangkat tanpa management IP')
                ->color($offline > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Critical Offline', number_format($criticalOffline))
                ->description('Perlu prioritas penanganan')
                ->color($criticalOffline > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-bolt'),
            Stat::make('Garansi < 90 Hari', number_format($warrantyExpiring))
                ->description('Perlu ditinjau untuk renewal/penggantian')
                ->color($warrantyExpiring > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Maintenance Terlewat', number_format($maintenanceOverdue))
                ->description('Jadwal maintenance yang perlu ditindaklanjuti')
                ->color($maintenanceOverdue > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-wrench-screwdriver'),
            Stat::make('Network', number_format(Network::query()->count()))
                ->description('Subnet yang terdokumentasi')
                ->color('info')
                ->icon('heroicon-o-globe-alt'),
        ];
    }
}
