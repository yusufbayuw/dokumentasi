<?php

namespace App\Livewire;

use App\Models\NetworkInterfaceSample;
use Filament\Widgets\ChartWidget;

class NetworkInterfaceTrafficChart extends ChartWidget
{
    public int $interfaceId = 0;

    protected static ?string $heading = 'Tren Traffic RX / TX (24 jam)';
    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.snmp.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getData(): array
    {
        abort_unless(static::canView(), 403);

        $measurements = NetworkInterfaceSample::query()
            ->where('device_interface_id', $this->interfaceId)
            ->where('checked_at', '>=', now()->subDay())
            ->orderByDesc('checked_at')
            ->limit(300)
            ->get(['checked_at', 'rx_mbps', 'tx_mbps'])
            ->reverse()
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'RX (Mbps)',
                    'data' => $measurements->map(fn (NetworkInterfaceSample $row): ?float =>
                        $row->rx_mbps === null ? null : (float) $row->rx_mbps)->all(),
                ],
                [
                    'label' => 'TX (Mbps)',
                    'data' => $measurements->map(fn (NetworkInterfaceSample $row): ?float =>
                        $row->tx_mbps === null ? null : (float) $row->tx_mbps)->all(),
                ],
            ],
            'labels' => $measurements->map(fn (NetworkInterfaceSample $row): string =>
                $row->checked_at->format('d/m H:i'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
