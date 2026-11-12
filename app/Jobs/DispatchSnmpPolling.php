<?php

namespace App\Jobs;

use App\Models\NetworkSnmpProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DispatchSnmpPolling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 45;

    public function handle(): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.snmp.enabled', false)) {
            return;
        }

        $limit = (int) config('network_quality.snmp.max_devices_per_cycle', 10);
        $interval = (int) config('network_quality.snmp.interval_minutes', 5);
        $sent = 0;

        NetworkSnmpProfile::query()
            ->where('enabled', true)
            ->when(! config('network_quality.snmp.v2c_enabled', false),
                fn ($query) => $query->where('version', '3'))
            ->whereHas('device', fn ($q) => $q->where('monitoring_enabled', true)
                ->whereNotIn('lifecycle_status', ['retired', 'disposed']))
            ->orderBy('id')
            ->chunkById(100, function ($profiles) use ($limit, $interval, &$sent): bool {
                foreach ($profiles as $profile) {
                    if ($sent >= $limit) {
                        break;
                    }

                    $key = 'network-quality:snmp:due:' . $profile->device_id;

                    if (Cache::add($key, now()->timestamp, max(45, $interval * 60))) {
                        PollDeviceSnmp::dispatch($profile->device_id)->onQueue('network-quality');
                        $sent++;
                    }
                }

                return $sent < $limit;
            });
    }
}
