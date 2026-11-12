<?php

namespace App\NetworkMonitoring;

class QualityClassifier
{
    public function classify(array $result): string
    {
        if (($result['status'] ?? 'error') === 'error') {
            return 'unknown';
        }

        if (($result['status'] ?? 'error') === 'down') {
            return 'bad';
        }

        if (($result['status'] ?? 'error') !== 'up') {
            return 'unknown';
        }

        $limits = config('network_quality.quality', []);
        $loss = $result['packet_loss_pct'] ?? null;
        $latency = $result['latency_ms'] ?? null;
        $variation = $result['rtt_variation_ms'] ?? null;

        if (($loss !== null && $loss >= ($limits['loss_bad_pct'] ?? 5))
            || ($latency !== null && $latency >= ($limits['latency_bad_ms'] ?? 350))
            || ($variation !== null && $variation >= ($limits['variation_bad_ms'] ?? 100))) {
            return 'bad';
        }

        if (($loss !== null && $loss >= ($limits['loss_degraded_pct'] ?? 1))
            || ($latency !== null && $latency >= ($limits['latency_degraded_ms'] ?? 150))
            || ($variation !== null && $variation >= ($limits['variation_degraded_ms'] ?? 50))) {
            return 'degraded';
        }

        return 'good';
    }
}
