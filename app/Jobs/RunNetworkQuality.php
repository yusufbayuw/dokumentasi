<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\NetworkQualityMeasurement;
use App\Models\NetworkQualityTarget;
use App\NetworkMonitoring\ProbeRunner;
use App\NetworkMonitoring\QualityClassifier;
use App\NetworkMonitoring\TargetGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunNetworkQuality implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 75;

    public function __construct(
        public ?int $deviceId = null,
        public ?int $targetId = null,
    ) {
    }

    public function handle(ProbeRunner $runner, QualityClassifier $classifier, TargetGuard $guard): void
    {
        if (! config('network_quality.enabled', false)) {
            return;
        }

        $device = null;
        $target = null;

        if ($this->deviceId !== null) {
            $device = Device::query()
                ->with(['ip', 'managementInterface.ipAddress'])
                ->find($this->deviceId);

            if (! $device || ! $device->monitoring_enabled
                || in_array($device->lifecycle_status, ['retired', 'disposed'], true)) {
                return;
            }

            $kind = 'icmp';
            $address = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;
            $port = null;
            $name = $device->nama;
            $samples = (int) config('network_quality.samples', 3);
            $timeout = (int) config('network_quality.timeout_seconds', 2);
            $expectedStatus = null;
            $monitorKey = 'device:' . $device->id;
        } elseif ($this->targetId !== null) {
            $target = NetworkQualityTarget::find($this->targetId);
            if (! $target || ! $target->enabled) {
                return;
            }

            $kind = $target->probe_type;
            $address = $target->address;
            $port = $target->port;
            $name = $target->name;
            $samples = (int) $target->samples;
            $timeout = (int) $target->timeout_seconds;
            $expectedStatus = $target->expected_status;
            $monitorKey = 'target:' . $target->id;
        } else {
            return;
        }

        if (! $address || ! $guard->allows($kind, $address, $port)) {
            // Do not attempt unapproved connections, even for stale queued jobs.
            return;
        }

        $result = $runner->run($kind, $address, $port, $samples, $timeout, $expectedStatus);

        NetworkQualityMeasurement::create([
            'device_id' => $device?->id,
            'target_id' => $target?->id,
            'monitor_key' => $monitorKey,
            'target_name' => $name,
            'address' => $address,
            'probe_type' => $kind,
            'status' => $result['status'],
            'quality' => $classifier->classify($result),
            'samples_sent' => $result['samples_sent'],
            'samples_received' => $result['samples_received'],
            'latency_ms' => $result['latency_ms'],
            'packet_loss_pct' => $result['packet_loss_pct'],
            'rtt_variation_ms' => $result['rtt_variation_ms'],
            'response_code' => $result['response_code'],
            'duration_ms' => $result['duration_ms'],
            'error_code' => $result['error_code'],
            'checked_at' => now(),
        ]);
    }
}
