<?php

namespace Tests\Unit;

use App\Models\NetworkQualityMeasurement;
use App\NetworkMonitoring\NetworkIncidentRules;
use App\NetworkMonitoring\ObservedAvailability;
use Illuminate\Support\Collection;
use Tests\TestCase;

class NetworkIncidentRulesTest extends TestCase
{
    private function checks(array $conditions): Collection
    {
        return collect($conditions)->map(fn (array $value) => (new NetworkQualityMeasurement())
            ->forceFill(['status' => $value[0], 'quality' => $value[1]]));
    }

    public function test_reopen_requires_fresh_consecutive_samples_after_resolution(): void
    {
        $rules = app(NetworkIncidentRules::class);
        $resolvedAt = \Carbon\Carbon::parse('2026-11-12 10:00:00');

        $old = $this->checks([['down', 'bad'], ['down', 'bad'], ['down', 'bad']]);
        foreach ($old as $i => $measurement) {
            $measurement->checked_at = $resolvedAt->copy()->subSeconds(10 + $i);
        }

        $this->assertFalse($rules->canReopen($old, $resolvedAt));

        foreach ($old as $i => $measurement) {
            $measurement->checked_at = $resolvedAt->copy()->addSeconds(10 - $i);
        }

        $this->assertTrue($rules->canReopen($old, $resolvedAt));
    }

    public function test_one_bad_sample_does_not_open_an_incident(): void
    {
        $this->assertNull(app(NetworkIncidentRules::class)->decide(
            $this->checks([['up', 'bad']])
        ));
    }

    public function test_consecutive_bad_or_down_samples_trigger_critical_incidents(): void
    {
        $rules = app(NetworkIncidentRules::class);

        $this->assertSame(
            ['action' => 'open', 'kind' => 'quality', 'severity' => 'critical'],
            $rules->decide($this->checks([['up','bad'],['up','bad'],['up','bad']]))
        );
        $this->assertSame(
            ['action' => 'open', 'kind' => 'unreachable', 'severity' => 'critical'],
            $rules->decide($this->checks([['down','bad'],['down','bad'],['down','bad']]))
        );
    }

    public function test_probe_errors_are_collector_incidents_not_network_down(): void
    {
        $this->assertSame(
            ['action' => 'open', 'kind' => 'collector', 'severity' => 'warning'],
            app(NetworkIncidentRules::class)->decide(
                $this->checks([['error','unknown'],['error','unknown'],['error','unknown']])
            )
        );
    }

    public function test_two_good_samples_resolve_and_availability_excludes_errors(): void
    {
        $this->assertSame(
            ['action' => 'resolve'],
            app(NetworkIncidentRules::class)->decide(
                $this->checks([['up','good'],['up','good'],['down','bad']])
            )
        );
        $this->assertSame(75.0, ObservedAvailability::percent(3, 1));
        $this->assertNull(ObservedAvailability::percent(0, 0));
    }
}
