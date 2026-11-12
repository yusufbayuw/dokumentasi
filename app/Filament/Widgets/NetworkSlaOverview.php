<?php

namespace App\Filament\Widgets;

use App\NetworkMonitoring\ObservedAvailability;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class NetworkSlaOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.incidents.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getStats(): array
    {
        $totals = DB::table('network_sla_daily')
            ->where('report_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('COALESCE(SUM(total_samples), 0) AS total,
                COALESCE(SUM(up_samples), 0) AS up,
                COALESCE(SUM(down_samples), 0) AS down,
                COALESCE(SUM(error_samples), 0) AS error')
            ->first();

        $up = (int) ($totals->up ?? 0);
        $down = (int) ($totals->down ?? 0);
        $available = ObservedAvailability::percent($up, $down);

        return [
            Stat::make('Sampel (30 hari)', number_format((int) ($totals->total ?? 0)))
                ->description('Jumlah seluruh hasil, termasuk error kolektor'),
            Stat::make('Terjangkau saat diperiksa', $available === null ? '-' : number_format($available, 2).'%')
                ->description('Rasio sampel up/(up + down), bukan jaminan uptime')
                ->color($available === null ? 'gray' : ($available >= 99 ? 'success' : 'warning')),
            Stat::make('Sampel gagal diukur', number_format((int) ($totals->error ?? 0)))
                ->description('Tidak dihitung sebagai downtime')
                ->color('gray'),
        ];
    }
}
