<?php

namespace App\Filament\Widgets;

use App\Services\NetworkQualitySnapshot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkIspOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.remote.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getStats(): array
    {
        $groups = NetworkQualitySnapshot::query()
            ->whereNotNull('probe_location_id')
            ->limit(5000)
            ->get(['isp_name', 'quality'])
            ->groupBy('isp_name')
            ->sortKeys();

        if ($groups->isEmpty()) {
            return [
                Stat::make('Hasil Probe ISP', '0')
                    ->description('Belum ada pengukuran remote yang diterima')
                    ->color('gray'),
            ];
        }

        return $groups->take(8)->map(function ($rows, $isp): Stat {
            $bad = $rows->where('quality', 'bad')->count();
            $degraded = $rows->where('quality', 'degraded')->count();

            return Stat::make((string) $isp, $rows->count().' target')
                ->description($bad.' buruk, '.$degraded.' menurun')
                ->color($bad > 0 ? 'danger' : ($degraded > 0 ? 'warning' : 'success'));
        })->values()->all();
    }
}
