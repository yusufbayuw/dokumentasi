<?php

namespace Tests\Unit;

use App\NetworkMonitoring\QualityClassifier;
use Tests\TestCase;

class NetworkQualityClassifierTest extends TestCase
{
    public function test_outcome_is_distinct_from_quality(): void
    {
        $classifier = app(QualityClassifier::class);

        $this->assertSame('unknown', $classifier->classify(['status' => 'error']));
        $this->assertSame('bad', $classifier->classify(['status' => 'down']));
        $this->assertSame('good', $classifier->classify(['status' => 'up', 'latency_ms' => 20, 'packet_loss_pct' => 0]));
        $this->assertSame('degraded', $classifier->classify(['status' => 'up', 'latency_ms' => 200]));
        $this->assertSame('bad', $classifier->classify(['status' => 'up', 'packet_loss_pct' => 30]));
        $this->assertSame('unknown', $classifier->classify(['status' => 'unknown']));
    }
}
