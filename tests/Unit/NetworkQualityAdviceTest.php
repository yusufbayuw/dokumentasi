<?php

namespace Tests\Unit;

use App\Models\NetworkQualityMeasurement;
use App\Services\NetworkQualityAdvice;
use App\Services\NetworkQualitySnapshot;
use Tests\TestCase;

class NetworkQualityAdviceTest extends TestCase
{
    public function test_unknown_is_not_labelled_as_offline(): void
    {
        $measurement = (new NetworkQualityMeasurement())->forceFill([
            'status' => 'error',
            'quality' => 'unknown',
            'probe_type' => 'icmp',
            'error_code' => 'icmp_unavailable',
        ]);

        $advice = NetworkQualityAdvice::forMeasurement($measurement);

        $this->assertStringContainsString('bukan bukti perangkat offline', $advice['summary']);
        $this->assertCount(3, $advice['steps']);
    }

    public function test_packet_loss_takes_priority_over_high_latency(): void
    {
        config(['network_quality.quality.loss_degraded_pct' => 1]);

        $measurement = (new NetworkQualityMeasurement())->forceFill([
            'status' => 'up',
            'quality' => 'bad',
            'probe_type' => 'icmp',
            'packet_loss_pct' => 33.33,
            'latency_ms' => 400,
        ]);

        $advice = NetworkQualityAdvice::forMeasurement($measurement);

        $this->assertStringContainsString('packet loss', $advice['summary']);
        $this->assertStringContainsString('gateway lokal', $advice['steps'][0]);
    }

    public function test_down_https_provides_specific_non_destructive_check(): void
    {
        $measurement = (new NetworkQualityMeasurement())->forceFill([
            'status' => 'down',
            'quality' => 'bad',
            'probe_type' => 'https',
        ]);

        $this->assertStringContainsString(
            'endpoint HTTPS',
            NetworkQualityAdvice::forMeasurement($measurement)['steps'][1],
        );
    }

    public function test_categories_are_allowlisted_and_share_one_window(): void
    {
        config(['network_quality.interval_minutes' => 5]);

        $this->assertSame('all', NetworkQualitySnapshot::validQuality('arbitrary'));
        $this->assertSame('degraded', NetworkQualitySnapshot::validQuality('degraded'));
        $this->assertSame(15, NetworkQualitySnapshot::windowMinutes());
    }
}
