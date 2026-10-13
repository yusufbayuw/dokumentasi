<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Jobs\RestartFingerprintDevice;
use App\Jobs\SyncFingerprintDevice;
use App\Models\Device;
use App\Support\FingerprintDevice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageFingerprint extends Page
{
    protected static string $resource = DeviceResource::class;
    protected static string $view = 'filament.resources.device-resource.pages.manage-fingerprint';

    public Device $record;
    public string $searchUser = '';
    public string $searchAttendanceUser = '';

    public function mount(int|string $record): void
    {
        $this->record = Device::with(['type', 'ip', 'managementInterface.ipAddress'])->findOrFail($record);

        abort_unless(FingerprintDevice::isSupported($this->record), 404);
        abort_unless($this->canView(), 403);
    }

    public function getTitle(): string
    {
        return 'Kelola Fingerprint: '.$this->record->nama;
    }

    public function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && (
            $user->hasRole('super_admin') ||
            $user->can('view_device') ||
            $user->can('view_any_device')
        ));
    }

    public function canManage(): bool
    {
        return FingerprintDevice::canRestart($this->record, auth()->user());
    }

    public function canSetClock(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $this->canManage() &&
            ($user->hasRole('super_admin') || $user->can('manage_fingerprint_clock')));
    }

    public function canViewSensitive(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('super_admin') || $user->can('view_fingerprint_data')));
    }

    // Filament actions are registered by method name and rendered explicitly
    // in the page body, rather than depending on header placement.
    public function informationAction(): Action
    {
        return Action::make('information')
            ->label('Baca Info Mesin')
            ->icon('heroicon-o-information-circle')
            ->visible(fn (): bool => $this->canManage())
            ->action(fn () => $this->enqueue('information'));
    }

    public function usersAction(): Action
    {
        return Action::make('users')
            ->label('Sinkron Pengguna')
            ->icon('heroicon-o-users')
            ->visible(fn (): bool => $this->canManage() && $this->canViewSensitive())
            ->action(fn () => $this->enqueue('users'));
    }

    public function attendanceAction(): Action
    {
        return Action::make('attendance')
            ->label('Tarik Log Absensi')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (): bool => $this->canManage() && $this->canViewSensitive())
            ->action(fn () => $this->enqueue('attendance'));
    }

    public function clockAction(): Action
    {
        return Action::make('clock')
            ->label('Samakan Jam')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Jam mesin akan disesuaikan dengan timezone server aplikasi. Periksa timezone sebelum melanjutkan.')
            ->visible(fn (): bool => $this->canSetClock())
            ->action(fn () => $this->enqueue('clock'));
    }

    public function rebootAction(): Action
    {
        return Action::make('reboot')
            ->label('Reboot')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Mesin akan terputus sementara. Hindari jam absensi.')
            ->visible(fn (): bool => $this->canManage())
            ->action(function (): void {
                abort_unless($this->canManage() && FingerprintDevice::isSupported($this->record), 403);

                RestartFingerprintDevice::dispatch($this->record->id, auth()->id(), 'manual')
                    ->onQueue('device-actions');

                Notification::make()->title('Perintah reboot diantrikan')->success()->send();
            });
    }

    public function enqueue(string $operation): void
    {
        abort_unless($this->canManage() && FingerprintDevice::isSupported($this->record), 403);

        if ($operation === 'clock') {
            abort_unless($this->canSetClock(), 403);
        }

        if (in_array($operation, ['users', 'attendance'], true)) {
            abort_unless($this->canViewSensitive(), 403);
        }

        abort_unless(in_array($operation, ['information', 'users', 'attendance', 'clock'], true), 404);

        SyncFingerprintDevice::dispatch($this->record->id, (int) auth()->id(), $operation)
            ->onQueue('device-actions');

        Notification::make()
            ->title('Operasi diantrikan')
            ->body('Lihat tab Riwayat Operasi setelah queue worker menyelesaikan permintaan.')
            ->success()
            ->send();
    }

    public function exportAttendance(): StreamedResponse
    {
        abort_unless($this->canViewSensitive() && FingerprintDevice::isSupported($this->record), 403);
        $deviceId = $this->record->id;

        return response()->streamDownload(function () use ($deviceId): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID Pengguna', 'UID', 'Waktu Mesin', 'State', 'Type']);

            DB::table('fingerprint_attendance_logs')
                ->where('device_id', $deviceId)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($stream): void {
                    foreach ($rows as $row) {
                        // Prevent spreadsheet formula injection from device-supplied IDs.
                        $id = (string) $row->user_id;
                        if (preg_match('/^[\s]*[=+\-@]/', $id)) {
                            $id = "'".$id;
                        }
                        fputcsv($stream, [$id, $row->uid, $row->event_at, $row->state, $row->type]);
                    }
                });

            fclose($stream);
        }, 'fingerprint-absensi-'.$deviceId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function getViewData(): array
    {
        abort_unless($this->canView() && FingerprintDevice::isSupported($this->record), 403);

        $id = $this->record->id;
        $snapshot = DB::table('fingerprint_device_snapshots')->where('device_id', $id)->first();
        $sensitive = $this->canViewSensitive();

        $users = collect();
        $attendance = collect();
        $userCount = 0;
        $attendanceCount = 0;

        if ($sensitive) {
            $userQuery = DB::table('fingerprint_device_users')->where('device_id', $id);
            $eventQuery = DB::table('fingerprint_attendance_logs')->where('device_id', $id);
            $userCount = (clone $userQuery)->count();
            $attendanceCount = (clone $eventQuery)->count();

            $users = $userQuery
                ->when($this->searchUser !== '', fn ($q) => $q->where(function ($nested) {
                    $nested->where('user_id', 'like', '%'.$this->searchUser.'%')
                        ->orWhere('name', 'like', '%'.$this->searchUser.'%');
                }))
                ->orderBy('user_id')->limit(100)->get();

            $attendance = $eventQuery
                ->when($this->searchAttendanceUser !== '', fn ($q) =>
                    $q->where('user_id', 'like', '%'.$this->searchAttendanceUser.'%'))
                ->orderByDesc('event_at')->orderByDesc('id')->limit(100)->get();
        }

        return [
            'information' => $snapshot && $snapshot->information
                ? (json_decode($snapshot->information, true) ?: []) : [],
            'snapshot' => $snapshot,
            'users' => $users,
            'attendance' => $attendance,
            'userCount' => $userCount,
            'attendanceCount' => $attendanceCount,
            'sensitive' => $sensitive,
            'runs' => DB::table('fingerprint_sync_runs')
                ->where('device_id', $id)
                ->orderByDesc('id')->limit(30)->get(),
        ];
    }
}
