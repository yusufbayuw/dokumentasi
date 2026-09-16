<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\IpAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PingIpAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $assignedIpIds = Device::query()
            ->where('monitoring_enabled', true)
            ->whereNotNull('ip_address_id')
            ->pluck('ip_address_id')
            ->filter()
            ->unique();

        if ($assignedIpIds->isEmpty()) {
            return;
        }

        IpAddress::query()
            ->whereIn('id', $assignedIpIds)
            ->get(['id', 'nama'])
            ->each(function (IpAddress $ipAddress): void {
                SinglePingIpAddress::dispatch($ipAddress->nama, $ipAddress->id);
            });
    }
}
