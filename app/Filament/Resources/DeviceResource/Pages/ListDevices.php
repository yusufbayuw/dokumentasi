<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use Filament\Actions;
use App\Models\TypeDevice;
use App\Services\FingerprintBulkRebootService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\DeviceResource;
use EightyNine\ExcelImport\ExcelImportAction;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make('import')
                ->color("info")
                ->icon('heroicon-o-arrow-up-tray'),
            Actions\CreateAction::make(),
            Actions\Action::make('rebootAllFingerprint')
                ->label('Reboot All')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->visible(fn (): bool => FingerprintBulkRebootService::canRequest(auth()->user()))
                ->requiresConfirmation()
                ->modalHeading('Reboot semua mesin fingerprint?')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalDescription(function (): string {
                    $plan = app(FingerprintBulkRebootService::class)->plan();

                    return "Ditemukan {$plan['total']} fingerprint; {$plan['eligible']} dengan IPv4 management valid "
                        ."akan diantrikan, sedangkan {$plan['skipped']} dilewati. "
                        .'Mesin dijadwalkan bertahap tiap 2 detik tanpa memerhatikan filter/tabs tabel. '
                        .'Ini dapat mengganggu absensi dan bukan operasi yang bisa dibatalkan otomatis.';
                })
                ->form([
                    TextInput::make('confirmation')
                        ->label('Ketik REBOOT ALL untuk mengonfirmasi')
                        ->placeholder('REBOOT ALL')
                        ->required()
                        ->rules(['in:REBOOT ALL']),
                ])
                ->modalSubmitActionLabel('Jadwalkan Reboot All')
                ->action(function (array $data): void {
                    abort_unless(FingerprintBulkRebootService::canRequest(auth()->user()), 403);
                    abort_unless(($data['confirmation'] ?? null) === 'REBOOT ALL', 403);

                    try {
                        $result = app(FingerprintBulkRebootService::class)->enqueue(auth()->user());

                        Notification::make()
                            ->title('Reboot All berhasil dijadwalkan')
                            ->body("{$result['queued']} fingerprint diantrikan bertahap setiap 2 detik; "
                                ."{$result['skipped']} dilewati karena IPv4 belum valid. "
                                .'Pastikan worker khusus queue fingerprint aktif.')
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('Gagal menjadwalkan Reboot All fingerprint.', [
                            'requested_by' => auth()->id(),
                            'error' => $exception->getMessage(),
                        ]);

                        Notification::make()
                            ->title('Reboot All tidak berhasil dijadwalkan sepenuhnya')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    public function exportFingerprintAttendance(int $deviceId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $device = \App\Models\Device::with('type')->findOrFail($deviceId);

        return \App\Support\FingerprintManagement::exportAttendance($device, auth()->user());
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')->badge($this->getModel()::count())];

        $types = TypeDevice::withCount('device')
            ->get();

        foreach ($types as $type) {
            $name = $type->kode;
            $slug = str($name)->slug()->toString();

            $tabs[$slug] = Tab::make($name)
                ->badge($type->device_count)
                ->modifyQueryUsing(function ($query) use ($type) {
                    return $query->where('type_device_id', $type->id);
                });
        }

        return $tabs;
    }
}
