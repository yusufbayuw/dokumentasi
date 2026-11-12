<?php

namespace App\NetworkMonitoring;

use Illuminate\Support\Collection;

class NetworkIncidentRules
{
    /**
     * Measurements must be newest first. 'error' is a collector incident,
     * never proof of a network outage. Two consecutive healthy checks resolve.
     *
     * @param Collection<int, \App\Models\NetworkQualityMeasurement> $latest
     * @return array{action:string,kind?:string,severity?:string}|null
     */
    public function decide(Collection $latest): ?array
    {
        $toOpen = (int) config('network_quality.incidents.bad_samples_to_open', 3);
        $toResolve = (int) config('network_quality.incidents.good_samples_to_resolve', 2);

        if ($latest->count() >= $toResolve && $latest->take($toResolve)
            ->every(fn ($m): bool => $m->status === 'up' && $m->quality === 'good')) {
            return ['action' => 'resolve'];
        }

        if ($latest->count() < $toOpen) {
            return null;
        }

        $head = $latest->take($toOpen);
        if ($head->every(fn ($m): bool => $m->status === 'down')) {
            return ['action' => 'open', 'kind' => 'unreachable', 'severity' => 'critical'];
        }

        if ($head->every(fn ($m): bool => $m->status === 'error')) {
            return ['action' => 'open', 'kind' => 'collector', 'severity' => 'warning'];
        }

        if ($head->every(fn ($m): bool => $m->status === 'up' && $m->quality === 'bad')) {
            return ['action' => 'open', 'kind' => 'quality', 'severity' => 'critical'];
        }

        if ($head->every(fn ($m): bool => $m->status === 'up'
            && in_array($m->quality, ['degraded', 'bad'], true))) {
            return ['action' => 'open', 'kind' => 'quality', 'severity' => 'warning'];
        }

        return null;
    }
}
