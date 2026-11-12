<?php

namespace App\Filament\Widgets;

use App\Models\NetworkQualityMeasurement;
use Filament\Widgets\ChartWidget;

class NetworkQualityLatencyChart extends ChartWidget
{
    protected static ?string $heading = 'Tren rata-rata RTT ICMP (24 jam)';
    protected static ?string $maxHeight = '240px';

    public static function canView(): bool
    {
        return config('network_quality.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getData(): array
    {
        $hours = collect(range(23, 0))
            ->map(fn (int $offset) => now()->startOfHour()->subHours($offset));

        $groups = NetworkQualityMeasurement::query()
            ->where('probe_type', 'icmp')
            ->where('status', 'up')
            ->whereNotNull('latency_ms')
            ->where('checked_at', '>=', $hours->first())
            ->orderByDesc('checked_at')
            ->limit(5000)
            ->get(['latency_ms', 'checked_at'])
            ->groupBy(fn (NetworkQualityMeasurement $m): string => $m->checked_at->format('Y-m-d H'));

        $values = $hours->map(function ($hour) use ($groups): ?float {
            $sample = $groups->get($hour->format('Y-m-d H'));

            return $sample && $sample->isNotEmpty()
                ? round((float) $sample->avg('latency_ms'), 2)
                : null;
        })->all();

        return [
            'datasets' => [
                ['label' => 'RTT rata-rata (ms)', 'data' => $values],
            ],
            'labels' => $hours->map(fn ($hour): string => $hour->format('H:i'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
