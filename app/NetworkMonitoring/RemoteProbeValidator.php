<?php

namespace App\NetworkMonitoring;

use App\Models\NetworkQualityTarget;
use DateTimeInterface;

class RemoteProbeValidator
{
    public function __construct(private TargetGuard $guard)
    {
    }

    public function fresh(DateTimeInterface $time, DateTimeInterface $now): bool
    {
        return abs($time->getTimestamp() - $now->getTimestamp())
            <= (int) config('network_quality.remote.max_skew_seconds', 300);
    }

    public function authorizedTarget(NetworkQualityTarget $target, array $data): bool
    {
        return $target->enabled
            && $target->probe_type === ($data['probe_type'] ?? null)
            && $target->address === ($data['address'] ?? null)
            && (int) ($target->port ?? 0) === (int) ($data['port'] ?? 0)
            && $this->guard->allows($target->probe_type, $target->address, $target->port);
    }
}
