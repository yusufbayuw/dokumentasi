<?php

namespace Tests\Unit;

use App\Models\NetworkProbeLocation;
use App\Models\NetworkQualityTarget;
use App\NetworkMonitoring\RemoteProbeValidator;
use Carbon\Carbon;
use Tests\TestCase;

class RemoteProbeValidatorTest extends TestCase
{
    public function test_write_only_remote_token_is_hashed_and_hidden(): void
    {
        $location = new NetworkProbeLocation();
        $token = str_repeat('x', 64);
        $location->api_token = $token;

        $this->assertSame(hash('sha256', $token), $location->getAttributes()['token_hash']);
        $this->assertArrayNotHasKey('token_hash', $location->toArray());
        $this->assertNull($location->api_token);
    }

    public function test_freshness_checks_both_old_and_future_reports(): void
    {
        config(['network_quality.remote.max_skew_seconds' => 300]);
        $check = app(RemoteProbeValidator::class);
        $now = Carbon::parse('2026-11-12 10:00:00');

        $this->assertTrue($check->fresh($now->copy()->subSeconds(299), $now));
        $this->assertFalse($check->fresh($now->copy()->subSeconds(301), $now));
        $this->assertFalse($check->fresh($now->copy()->addSeconds(301), $now));
    }

    public function test_report_cannot_substitute_another_target_address_or_method(): void
    {
        $target = new NetworkQualityTarget();
        $target->enabled = true;
        $target->probe_type = 'icmp';
        $target->address = '10.10.1.1';
        $target->port = null;
        $check = app(RemoteProbeValidator::class);

        $valid = ['probe_type' => 'icmp', 'address' => '10.10.1.1', 'port' => null];

        $this->assertTrue($check->authorizedTarget($target, $valid));
        $this->assertFalse($check->authorizedTarget($target, array_replace($valid, ['address' => '10.10.1.2'])));
        $this->assertFalse($check->authorizedTarget($target, array_replace($valid, ['probe_type' => 'https'])));
    }
}
