<?php

namespace App\Filament\Widgets;

use App\Services\CmdbDataQualityService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DataQualityOverview extends BaseWidget
{
    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    protected function getStats(): array
    {
        $issues = collect(app(CmdbDataQualityService::class)->issues());
        $active = $issues->where('count', '>', 0);

        $danger = (int) $active->where('severity', 'danger')->sum('count');
        $warning = (int) $active->where('severity', 'warning')->sum('count');
        $info = (int) $active->where('severity', 'info')->sum('count');

        $activeRules = $active->count();
        $passedRules = max(0, $issues->count() - $activeRules);

        return [
            Stat::make('Pemeriksaan Bermasalah', number_format($activeRules))
                ->description(number_format($passedRules) . ' pemeriksaan bersih dari ' . number_format($issues->count()) . ' total')
                ->color($activeRules > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Temuan Kritis', number_format($danger))
                ->description('Prioritas tertinggi untuk diperbaiki')
                ->color($danger > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-circle'),
            Stat::make('Peringatan', number_format($warning))
                ->description('Perlu tindak lanjut operasional')
                ->color($warning > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Kelengkapan Data', number_format($info))
                ->description('Peluang peningkatan kualitas metadata')
                ->color($info > 0 ? 'info' : 'success')
                ->icon('heroicon-o-information-circle'),
        ];
    }
}
