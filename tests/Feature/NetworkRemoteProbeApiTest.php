<?php

namespace Tests\Feature;

use App\Models\NetworkProbeLocation;
use Tests\TestCase;

class NetworkRemoteProbeApiTest extends TestCase
{
    public function test_remote_reports_are_rejected_when_disabled(): void
    {
        config(['network_quality.enabled' => true, 'network_quality.remote.enabled' => false]);

        $this->postJson('/api/network-quality/probe-results', [])
            ->assertStatus(404);
    }

    public function test_missing_token_cannot_report_even_if_feature_is_enabled(): void
    {
        config(['network_quality.enabled' => true, 'network_quality.remote.enabled' => true]);

        $this->postJson('/api/network-quality/probe-results', ['location_id' => 1])
            ->assertStatus(401);
    }

    public function test_token_and_isp_are_not_exposed_by_the_model(): void
    {
        $model = new NetworkProbeLocation();
        $model->name = 'Probe A';
        $model->isp_name = 'ISP A';
        $model->api_token = str_repeat('A', 64);

        $this->assertArrayNotHasKey('token_hash', $model->toArray());
        $this->assertSame('ISP A', $model->isp_name);
    }
}
