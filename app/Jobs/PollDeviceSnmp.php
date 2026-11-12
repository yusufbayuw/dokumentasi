<?php

namespace App\Jobs;

use App\NetworkMonitoring\SnmpInterfacePoller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollDeviceSnmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 75;

    public function __construct(public int $deviceId)
    {
    }

    public function handle(SnmpInterfacePoller $poller): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.snmp.enabled', false)
            || ! config('network_quality.snmp.legacy_interface_metrics_enabled', false)) {
            return;
        }

        $poller->poll($this->deviceId);
    }
}
