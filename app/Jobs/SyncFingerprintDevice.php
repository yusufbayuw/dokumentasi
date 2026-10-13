<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\User;
use App\Services\FingerprintService;
use App\Support\FingerprintDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncFingerprintDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct(
        public int $deviceId,
        public int $requestedBy,
        public string $operation,
    ) {
    }

    public function handle(FingerprintService $service): void
    {
        if (! in_array($this->operation, ['information', 'users', 'attendance', 'clock'], true)) {
            throw new RuntimeException('Operasi fingerprint tidak dikenal.');
        }

        $device = Device::with(['type', 'ip', 'managementInterface.ipAddress'])->findOrFail($this->deviceId);
        $user = User::find($this->requestedBy);

        // Recheck permissions when the queued action actually executes.
        if (! FingerprintDevice::isSupported($device) || ! $user || ! ($user->hasRole('super_admin') || $user->can('update_device'))) {
            throw new RuntimeException('Hak akses fingerprint sudah tidak berlaku.');
        }

        if (in_array($this->operation, ['users', 'attendance'], true) &&
            ! ($user->hasRole('super_admin') || $user->can('view_fingerprint_data'))) {
            throw new RuntimeException('Tidak berhak membaca data kehadiran fingerprint.');
        }

        if ($this->operation === 'clock' &&
            ! ($user->hasRole('super_admin') || $user->can('manage_fingerprint_clock'))) {
            throw new RuntimeException('Tidak berhak mengubah jam mesin fingerprint.');
        }

        $lock = Cache::lock('fingerprint-device-'.$device->id, 210);
        if (! $lock->get()) {
            throw new RuntimeException('Operasi lain sedang berjalan pada mesin ini.');
        }

        try {
            $runId = DB::table('fingerprint_sync_runs')->insertGetId([
                'device_id' => $device->id,
                'requested_by' => $user->id,
                'operation' => $this->operation,
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $count = match ($this->operation) {
                    'information' => $this->saveInformation($device, $service),
                    'users' => $this->saveUsers($device, $service),
                    'attendance' => $this->saveAttendance($device, $service),
                    'clock' => $this->setClock($device, $service),
                };

                DB::table('fingerprint_sync_runs')->where('id', $runId)->update([
                    'status' => 'success',
                    'records' => $count,
                    'updated_at' => now(),
                ]);
            } catch (Throwable $exception) {
                DB::table('fingerprint_sync_runs')->where('id', $runId)->update([
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 300),
                    'updated_at' => now(),
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    private function saveInformation(Device $device, FingerprintService $service): int
    {
        $info = $service->information($device);
        $this->upsertSnapshot($device, ['information' => json_encode($info), 'information_synced_at' => now()]);

        return 1;
    }

    private function saveUsers(Device $device, FingerprintService $service): int
    {
        $users = $service->users($device);
        $now = now();
        $rows = array_map(fn (array $user): array => [
            'device_id' => $device->id,
            'user_id' => $user['user_id'],
            'uid' => $user['uid'],
            'name' => $user['name'],
            'role' => $user['role'],
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $users);

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('fingerprint_device_users')->upsert(
                $chunk, ['device_id', 'user_id'], ['uid', 'name', 'role', 'last_seen_at', 'updated_at'],
            );
        }

        // Never delete local users on an empty/partial device response.
        $this->upsertSnapshot($device, ['users_synced_at' => $now]);

        return count($users);
    }

    private function saveAttendance(Device $device, FingerprintService $service): int
    {
        $events = $service->attendance($device);
        $now = now();
        $rows = array_map(fn (array $event): array => array_merge($event, [
            'device_id' => $device->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $events);

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('fingerprint_attendance_logs')->insertOrIgnore($chunk);
        }

        // Idempotent: machine logs are never cleared by a sync.
        $this->upsertSnapshot($device, ['attendance_synced_at' => $now]);

        return count($events);
    }

    private function setClock(Device $device, FingerprintService $service): int
    {
        $service->syncClock($device);

        return 1;
    }

    private function upsertSnapshot(Device $device, array $fields): void
    {
        DB::table('fingerprint_device_snapshots')->upsert(
            [array_merge([
                'device_id' => $device->id,
                'created_at' => now(),
                'updated_at' => now(),
            ], $fields)],
            ['device_id'],
            [...array_keys($fields), 'updated_at'],
        );
    }
}
