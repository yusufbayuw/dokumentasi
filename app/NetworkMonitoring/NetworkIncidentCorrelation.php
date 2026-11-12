<?php

namespace App\NetworkMonitoring;

use App\Models\DeviceConnection;
use App\Models\NetworkQualityMeasurement;

/**
 * Read-only, indicative correlation based on CMDB links and probe vantage
 * points. Never claims or executes verified root-cause remediation.
 */
class NetworkIncidentCorrelation
{
    public function hint(NetworkQualityMeasurement $measurement): string
    {
        if ($measurement->status === 'error') {
            return 'Pengukuran gagal: periksa worker, izin target, dan jalur monitoring terlebih dahulu. Ini bukan bukti layanan mati.';
        }

        if ($measurement->device_id !== null) {
            $id = (int) $measurement->device_id;
            $links = DeviceConnection::query()->where('source_device_id', $id)
                ->orWhere('target_device_id', $id)
                ->limit(30)->get(['source_device_id', 'target_device_id']);

            $neighbors = $links->map(fn ($l): int => (int) (
                $l->source_device_id === $id ? $l->target_device_id : $l->source_device_id
            ))->filter(fn (int $neighbor): bool => $neighbor !== $id && $neighbor > 0)
                ->unique()->take(10);

            $down = 0;
            foreach ($neighbors as $neighbor) {
                $last = NetworkQualityMeasurement::query()
                    ->where('device_id', $neighbor)
                    ->where('checked_at', '>=', now()->subMinutes(15))
                    ->orderByDesc('checked_at')->first(['status']);
                if ($last?->status === 'down') {
                    $down++;
                }
            }

            if ($down > 0) {
                return 'Ada '.$down.' perangkat tetangga pada topologi CMDB yang juga tidak terjangkau. Periksa titik koneksi bersama; ini belum membuktikan penyebab utama.';
            }

            return 'Belum ada gangguan tetangga yang terkonfirmasi dari catatan topologi. Periksa interface, kabel, uplink dan gateway.';
        }

        if ($measurement->target_id !== null) {
            $other = NetworkQualityMeasurement::query()
                ->where('target_id', $measurement->target_id)
                ->where('monitor_key', '!=', $measurement->monitor_key)
                ->where('checked_at', '>=', now()->subMinutes(15))
                ->orderByDesc('checked_at')
                ->limit(100)->get(['monitor_key', 'status'])
                ->unique('monitor_key');

            if ($other->contains(fn ($m): bool => $m->status === 'down')) {
                return 'Target yang sama dilaporkan tidak terjangkau dari titik ukur lain. Periksa layanan tujuan dan jalur bersama; verifikasi sebelum menyimpulkan sumber gangguan.';
            }

            if ($other->isNotEmpty()) {
                return 'Hasil dari lokasi probe lain berbeda atau belum menunjukkan kegagalan serupa. Periksa ISP/jalur lokal dan titik ukur masing-masing.';
            }
        }

        return 'Periksa histori pengukuran, gateway, DNS/service dan jalur terkait; sistem belum dapat memastikan akar masalah.';
    }
}
