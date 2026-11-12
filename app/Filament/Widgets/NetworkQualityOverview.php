<?php

namespace App\Filament\Widgets;

use App\Models\NetworkQualityMeasurement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class NetworkQualityOverview extends BaseWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return config('network_quality.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getStats(): array
    {
        $minutes = max(15, (int) config('network_quality.interval_minutes', 5) * 3);

        $latestIds = DB::table('network_quality_measurements')
            ->selectRaw('MAX(id)')
            ->where('checked_at', '>=', now()->subMinutes($minutes))
            ->groupBy('monitor_key');

        $latest = NetworkQualityMeasurement::query()
            ->whereIn('id', $latestIds)
            ->get(['quality']);

        return [
            Stat::make('Target Diukur', number_format($latest->count()))
                ->description('Hasil terbaru dalam ' . $minutes . ' menit')
                ->icon('heroicon-o-signal'),
            Stat::make('Baik', number_format($latest->where('quality', 'good')->count()))
                ->description('Koneksi normal menurut ambang awal')
                ->color('success'),
            Stat::make('Menurun', number_format($latest->where('quality', 'degraded')->count()))
                ->description('Perlu observasi')
                ->color('warning'),
            Stat::make('Buruk', number_format($latest->where('quality', 'bad')->count()))
                ->description('Perlu ditelusuri')
                ->color('danger'),
            Stat::make('Belum diketahui', number_format($latest->where('quality', 'unknown')->count()))
                ->description('Periksa collector atau target')
                ->color('gray'),
        ];
    }
}
