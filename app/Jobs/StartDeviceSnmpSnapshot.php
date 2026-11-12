<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\NetworkSnmpProfile;
use App\Models\NetworkSnmpSnapshot;
use App\NetworkMonitoring\SnmpOidCursor;
use App\NetworkMonitoring\SnmpProtocolPolicy;
use App\NetworkMonitoring\TargetGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class StartDeviceSnmpSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public int $deviceId)
    {
    }

    public function handle(TargetGuard $guard): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.snmp.enabled', false)) {
            return;
        }

        $device = Device::query()->with([
            'ip', 'managementInterface.ipAddress', 'snmpProfile',
        ])->find($this->deviceId);

        if (! $device || ! $device->monitoring_enabled
            || in_array($device->lifecycle_status, ['retired', 'disposed'], true)
            || ! SnmpProtocolPolicy::canPoll($device->snmpProfile)) {
            return;
        }

        $host = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;
        if (! $host || ! $guard->allows('icmp', $host)) {
            $this->recordDiagnostic($device->snmpProfile, 'snmp_host_not_allowed');
            return;
        }

        $roots = SnmpOidCursor::validRoots(config('network_quality.snmp.snapshot_roots', []));
        if ($roots === []) {
            $this->recordDiagnostic($device->snmpProfile, 'snmp_invalid_oid_roots');
            return;
        }

        $profileId = $device->snmpProfile->id;
        $maxAge = (int) config('network_quality.snmp.snapshot_max_minutes', 90);

        $snapshotId = DB::transaction(function () use ($device, $roots, $profileId, $maxAge): ?int {
            // Serialize starts for the same profile, including scheduler/manual.
            NetworkSnmpProfile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            $running = NetworkSnmpSnapshot::query()
                ->where('network_snmp_profile_id', $profileId)
                ->where('status', 'running')
                ->lockForUpdate()->latest('id')->first();

            if ($running && $running->started_at->gt(now()->subMinutes($maxAge))) {
                return null; // An existing capture is still in progress.
            }

            if ($running) {
                $running->update([
                    'status' => 'partial',
                    'error_code' => 'snmp_snapshot_expired',
                    'finished_at' => now(),
                ]);
            }

            return NetworkSnmpSnapshot::create([
                'device_id' => $device->id,
                'network_snmp_profile_id' => $profileId,
                'snmp_version' => $device->snmpProfile->version,
                'status' => 'running',
                'roots' => $roots,
                'root_index' => 0,
                'cursor_oid' => null,
                'snapshot_max_oids' => (int) config('network_quality.snmp.snapshot_max_oids', 20000),
                'snapshot_max_value_bytes' => (int) config('network_quality.snmp.snapshot_max_value_bytes', 8192),
                'started_at' => now(),
            ])->id;
        });

        if ($snapshotId !== null) {
            CollectDeviceSnmpSnapshot::dispatch($snapshotId)->onQueue('network-quality');
        }
    }

    private function recordDiagnostic(NetworkSnmpProfile $profile, string $code): void
    {
        DB::table('network_snmp_profiles')->where('id', $profile->id)->update([
            'last_polled_at' => now(),
            'last_poll_error' => $code,
            'last_sample_count' => 0,
        ]);
    }
}
