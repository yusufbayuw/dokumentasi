<?php

namespace App\Support;

use App\Exports\FingerprintUsersExport;
use App\Jobs\RestartFingerprintDevice;
use App\Jobs\SyncFingerprintDevice;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FingerprintManagement
{
    public static function canView(?User $user): bool
    {
        return $user !== null && (
            $user->hasRole('super_admin') ||
            $user->can('view_device') ||
            $user->can('view_any_device')
        );
    }

    public static function canViewSensitive(?User $user): bool
    {
        return self::canView($user) && (
            $user->hasRole('super_admin') || $user->can('view_fingerprint_data')
        );
    }

    public static function canSetClock(Device $device, ?User $user): bool
    {
        return FingerprintDevice::canRestart($device, $user) && (
            $user->hasRole('super_admin') || $user->can('manage_fingerprint_clock')
        );
    }

    public static function operationOptions(Device $device, ?User $user): array
    {
        if (! FingerprintDevice::canRestart($device, $user)) {
            return [];
        }

        $options = ['information' => 'Baca Info Mesin'];

        if (self::canViewSensitive($user)) {
            $options['users'] = 'Sinkron Pengguna (baca saja)';
            $options['attendance'] = 'Tarik Log Absensi';
        }

        if (self::canSetClock($device, $user)) {
            $options['clock'] = 'Samakan Jam Mesin';
        }

        $options['reboot'] = 'Reboot Mesin';

        return $options;
    }

    public static function data(Device $device, ?User $user): array
    {
        abort_unless(FingerprintDevice::isSupported($device) && self::canView($user), 403);

        $device->loadMissing(['type', 'ip', 'managementInterface.ipAddress']);
        $id = $device->getKey();
        $snapshot = DB::table('fingerprint_device_snapshots')->where('device_id', $id)->first();
        $sensitive = self::canViewSensitive($user);
        $users = collect();
        $attendance = collect();
        $userCount = 0;
        $attendanceCount = 0;

        if ($sensitive) {
            $userQuery = DB::table('fingerprint_device_users')->where('device_id', $id);
            $attendanceQuery = DB::table('fingerprint_attendance_logs')->where('device_id', $id);

            $userCount = (clone $userQuery)->count();
            $attendanceCount = (clone $attendanceQuery)->count();
            $users = $userQuery->orderBy('user_id')->limit(100)->get();
            $attendance = $attendanceQuery->orderByDesc('event_at')->orderByDesc('id')->limit(100)->get();
        }

        return [
            'device' => $device,
            'snapshot' => $snapshot,
            'information' => $snapshot?->information ? (json_decode($snapshot->information, true) ?: []) : [],
            'sensitive' => $sensitive,
            'users' => $users,
            'userCount' => $userCount,
            'attendance' => $attendance,
            'attendanceCount' => $attendanceCount,
            'runs' => DB::table('fingerprint_sync_runs')
                ->where('device_id', $id)
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
        ];
    }

    public static function enqueue(Device $device, ?User $user, string $operation, bool $confirmed = false): void
    {
        abort_unless($user && FingerprintDevice::isSupported($device), 403);
        abort_unless(array_key_exists($operation, self::operationOptions($device, $user)), 403);
        abort_if(in_array($operation, ['clock', 'reboot'], true) && ! $confirmed, 422);

        if ($operation === 'reboot') {
            RestartFingerprintDevice::dispatch($device->getKey(), $user->getKey(), 'manual')
                ->onQueue(FingerprintQueue::NAME);

            return;
        }

        SyncFingerprintDevice::dispatch($device->getKey(), $user->getKey(), $operation)
            ->onQueue(FingerprintQueue::NAME);
    }

    public static function exportUsers(Device $device, ?User $user): BinaryFileResponse
    {
        abort_unless(FingerprintDevice::isSupported($device) && self::canViewSensitive($user), 403);

        $id = (int) $device->getKey();

        return Excel::download(new FingerprintUsersExport($id), 'fingerprint-pengguna-'.$id.'.xlsx');
    }

    public static function exportAttendance(Device $device, ?User $user): StreamedResponse
    {
        abort_unless(FingerprintDevice::isSupported($device) && self::canViewSensitive($user), 403);

        $id = $device->getKey();

        return response()->streamDownload(function () use ($id): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID Pengguna', 'UID', 'Waktu Mesin', 'State', 'Type']);

            DB::table('fingerprint_attendance_logs')
                ->where('device_id', $id)
                ->chunkById(500, function ($rows) use ($stream): void {
                    foreach ($rows as $row) {
                        $userId = (string) $row->user_id;
                        if (preg_match('/^[\s]*[=+\-@]/', $userId)) {
                            $userId = "'".$userId;
                        }

                        fputcsv($stream, [$userId, $row->uid, $row->event_at, $row->state, $row->type]);
                    }
                });

            fclose($stream);
        }, 'fingerprint-absensi-'.$id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
