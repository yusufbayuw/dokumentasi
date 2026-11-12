<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\NetworkQualityTarget;
use App\NetworkMonitoring\TargetGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DispatchNetworkQuality implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(TargetGuard $guard): void
    {
        if (! config('network_quality.enabled', false)) {
            return;
        }

        $maxJobs = (int) config('network_quality.max_jobs_per_cycle', 500);
        $queued = 0;
        $interval = max(1, (int) config('network_quality.interval_minutes', 5));

        Device::query()
            ->where('monitoring_enabled', true)
            ->whereNotIn('lifecycle_status', ['retired', 'disposed'])
            ->where(function ($query): void {
                $query->whereNotNull('ip_address_id')
                    ->orWhereHas('interfaces', fn ($q) => $q
                        ->where('is_management', true)->whereNotNull('ip_address_id'));
            })
            ->with(['ip', 'managementInterface.ipAddress'])
            ->chunkById(150, function ($devices) use ($guard, $interval, $maxJobs, &$queued): bool {
                foreach ($devices as $device) {
                    $ip = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;
                    if (! $ip || ! $guard->allows('icmp', $ip) || $queued >= $maxJobs) {
                        continue;
                    }

                    $key = 'device:' . $device->id;
                    if ($this->due($key, $interval)) {
                        RunNetworkQuality::dispatch($device->id, null)->onQueue('network-quality');
                        $queued++;
                    }
                }

                return $queued < $maxJobs;
            });

        if ($queued >= $maxJobs) {
            return;
        }

        NetworkQualityTarget::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->chunkById(150, function ($targets) use ($guard, $maxJobs, &$queued): bool {
                foreach ($targets as $target) {
                    if ($queued >= $maxJobs) {
                        break;
                    }

                    if (! $guard->allows($target->probe_type, $target->address, $target->port)) {
                        continue;
                    }

                    $key = 'target:' . $target->id;
                    if ($this->due($key, max(1, (int) $target->interval_minutes))) {
                        RunNetworkQuality::dispatch(null, $target->id)->onQueue('network-quality');
                        $queued++;
                    }
                }

                return $queued < $maxJobs;
            });
    }

    private function due(string $key, int $intervalMinutes): bool
    {
        // Cache::add is atomic for shared cache stores. Queue workload remains
        // separate from existing ICMP and fingerprint workers.
        return Cache::add(
            'network-quality:due:' . sha1($key),
            now()->timestamp,
            max(45, $intervalMinutes * 60),
        );
    }
}
