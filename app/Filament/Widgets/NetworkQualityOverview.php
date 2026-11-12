<?php

namespace App\Filament\Widgets;

use App\Services\NetworkQualitySnapshot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
        $minutes = NetworkQualitySnapshot::windowMinutes();
        $latest = NetworkQualitySnapshot::query()->get(['quality']);

        return [
            Stat::make('Target Diukur', number_format($latest->count()))
                ->description('Hasil terbaru dalam ' . $minutes . ' menit')
                ->icon('heroicon-o-signal')
                ->extraAttributes(self::detailAttributes('all', 'semua target')),
            Stat::make('Baik', number_format($latest->where('quality', 'good')->count()))
                ->description('Koneksi normal menurut ambang awal')
                ->color('success')
                ->extraAttributes(self::detailAttributes('good', 'kualitas baik')),
            Stat::make('Menurun', number_format($latest->where('quality', 'degraded')->count()))
                ->description('Perlu observasi')
                ->color('warning')
                ->extraAttributes(self::detailAttributes('degraded', 'kualitas menurun')),
            Stat::make('Buruk', number_format($latest->where('quality', 'bad')->count()))
                ->description('Perlu ditelusuri')
                ->color('danger')
                ->extraAttributes(self::detailAttributes('bad', 'kualitas buruk')),
            Stat::make('Belum diketahui', number_format($latest->where('quality', 'unknown')->count()))
                ->description('Periksa collector atau target')
                ->color('gray')
                ->extraAttributes(self::detailAttributes('unknown', 'kualitas belum diketahui')),
        ];
    }
    /**
     * Filament 3 Stat::extraAttributes supports native Livewire event dispatch.
     * Keyboard access is preserved for every interactive summary card.
     */
    private static function detailAttributes(string $quality, string $label): array
    {
        $dispatch = "\$dispatch('open-network-quality-details', { quality: '{$quality}' })";

        return [
            'class' => 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-xl',
            'role' => 'button',
            'tabindex' => '0',
            'aria-label' => "Buka rincian {$label}",
            'wire:click' => $dispatch,
            'wire:keydown.enter' => $dispatch,
            'wire:keydown.space.prevent' => $dispatch,
        ];
    }
}
