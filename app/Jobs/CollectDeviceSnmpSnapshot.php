<?php

namespace App\Jobs;

use App\Models\NetworkSnmpOid;
use App\Models\NetworkSnmpSnapshot;
use App\NetworkMonitoring\NativeSnmpTransport;
use App\NetworkMonitoring\SnmpProtocolPolicy;
use App\NetworkMonitoring\TargetGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CollectDeviceSnmpSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 70;
    public int $backoff = 10;

    public function __construct(public int $snapshotId)
    {
    }

    public function handle(NativeSnmpTransport $transport, TargetGuard $guard): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.snmp.enabled', false)) {
            return;
        }

        $lock = Cache::lock('snmp:snapshot:'.$this->snapshotId, 90);
        if (! $lock->get()) {
            // Another worker may still be finishing this snapshot chunk.
            // Do not lose the continuation when multiple workers are active.
            $this->release(5);
            return;
        }

        try {
            $snapshot = NetworkSnmpSnapshot::with([
                'snmpProfile', 'device.ip', 'device.managementInterface.ipAddress',
            ])->find($this->snapshotId);

            if (! $snapshot || $snapshot->status !== 'running') {
                return;
            }

            $device = $snapshot->device;
            $profile = $snapshot->snmpProfile;
            if (! $device || ! $device->monitoring_enabled
                || in_array($device->lifecycle_status, ['retired', 'disposed'], true)
                || ! SnmpProtocolPolicy::canPoll($profile)
                || $profile->version !== $snapshot->snmp_version) {
                $this->finish($snapshot, 'snmp_profile_changed');
                return;
            }

            if ($snapshot->started_at->lt(now()->subMinutes(
                (int) config('network_quality.snmp.snapshot_max_minutes', 90)
            ))) {
                $this->finish($snapshot, 'snmp_snapshot_expired');
                return;
            }

            $host = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;
            if (! $host || ! $guard->allows('icmp', $host)) {
                $this->finish($snapshot, 'snmp_host_not_allowed');
                return;
            }

            $roots = $snapshot->roots ?? [];
            $root = $roots[$snapshot->root_index] ?? null;
            if ($root === null) {
                $this->finish($snapshot);
                return;
            }

            $cursor = $snapshot->cursor_oid ?: $root;
            $remaining = (int) $snapshot->snapshot_max_oids - (int) $snapshot->oid_count;
            if ($remaining <= 0) {
                $this->finish($snapshot, 'snmp_oid_limit');
                return;
            }

            try {
                $result = $transport->nextSnapshotSegment(
                    $profile,
                    $host,
                    $root,
                    $cursor,
                    min((int) config('network_quality.snmp.snapshot_chunk_oids', 30), $remaining),
                    (int) config('network_quality.snmp.snapshot_chunk_seconds', 15),
                    (int) $snapshot->snapshot_max_value_bytes,
                );
            } catch (Throwable) {
                $this->finish($snapshot, 'snmp_transport_failed');
                return;
            }

            $added = 0;
            $bytes = 0;
            $limited = 0;

            // Persist individual values encrypted, not an unbounded array or
            // an attachment under the public webroot.
            foreach ($result['rows'] as $row) {
                $oid = NetworkSnmpOid::firstOrCreate([
                    'network_snmp_snapshot_id' => $snapshot->id,
                    'oid_hash' => hash('sha256', $row['oid']),
                ], [
                    'oid' => $row['oid'],
                    'value' => $row['value'],
                    'value_type' => $row['type'],
                    'encoding' => $row['encoding'],
                    'raw_bytes' => $row['raw_bytes'],
                    'truncated' => $row['truncated'],
                ]);

                if ($oid->wasRecentlyCreated) {
                    $added++;
                    $bytes += $row['raw_bytes'];
                    $limited += $row['truncated'] ? 1 : 0;
                }
            }

            $snapshot->oid_count += $added;
            $snapshot->collected_bytes += $bytes;
            $snapshot->limited_values += $limited;

            if ($result['rows'] !== []) {
                $snapshot->cursor_oid = $result['cursor'];
            }

            if ($result['error'] !== null) {
                $this->finish($snapshot, $result['error']);
                return;
            }

            if ($result['finished']) {
                $snapshot->root_index++;
                $snapshot->cursor_oid = null;
            } elseif ($result['rows'] === []) {
                $this->finish($snapshot, 'snmp_no_progress');
                return;
            }

            if ($snapshot->root_index >= count($roots)) {
                $this->finish($snapshot);
                return;
            }

            if ($snapshot->oid_count >= $snapshot->snapshot_max_oids) {
                $this->finish($snapshot, 'snmp_oid_limit');
                return;
            }

            $snapshot->save();
            // Continue on the same queue, one bounded page per job.
            self::dispatch($snapshot->id)->onQueue('network-quality')->delay(now()->addSeconds(3));
        } finally {
            $lock->release();
        }
    }

    private function finish(NetworkSnmpSnapshot $snapshot, ?string $error = null): void
    {
        $snapshot->status = $error === null && $snapshot->limited_values === 0
            ? 'complete'
            : ($snapshot->oid_count > 0 ? 'partial' : 'failed');
        $snapshot->error_code = $error ?? ($snapshot->limited_values > 0 ? 'snmp_value_limit' : null);
        $snapshot->finished_at = now();
        $snapshot->save();

        // Direct DB update avoids altering/decrypting credentials on profiles.
        DB::table('network_snmp_profiles')
            ->where('id', $snapshot->network_snmp_profile_id)
            ->update([
                'last_polled_at' => now(),
                'last_poll_error' => $snapshot->error_code,
                'last_sample_count' => $snapshot->oid_count,
            ]);
    }
}
