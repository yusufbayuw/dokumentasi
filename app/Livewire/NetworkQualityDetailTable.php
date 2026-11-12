<?php

namespace App\Livewire;

use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\NetworkQualityTargetResource;
use App\Models\NetworkQualityMeasurement;
use App\Jobs\RunNetworkQuality;
use App\Models\Unit;
use App\Services\NetworkQualityAdvice;
use App\Services\NetworkQualitySnapshot;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use Livewire\Component;

class NetworkQualityDetailTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public string $quality = 'all';

    public function mount(string $quality = 'all'): void
    {
        $this->authorizeViewing();

        $this->quality = NetworkQualitySnapshot::validQuality($quality);
    }

    private function authorizeViewing(): void
    {
        abort_unless(
            config('network_quality.enabled', false)
                && (auth()->user()?->can('view_any_device') ?? false),
            403,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkQualitySnapshot::query($this->quality)->with(['device.ownerUnit', 'target']))
            ->columns([
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Target')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('device.ownerUnit.nama')
                    ->label('Unit')->placeholder('Layanan')->toggleable(),
                Tables\Columns\TextColumn::make('probe_type')
                    ->label('Metode')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Konektivitas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'up' => 'Terjangkau',
                        'down' => 'Tidak terjangkau',
                        default => 'Gagal diukur',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success',
                        'down' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quality')
                    ->label('Kualitas')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'good' => 'Baik',
                        'degraded' => 'Menurun',
                        'bad' => 'Buruk',
                        default => 'Belum diketahui',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'good' => 'success',
                        'degraded' => 'warning',
                        'bad' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('RTT / Respons')->suffix(' ms')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('packet_loss_pct')
                    ->label('Loss')->suffix('%')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Diperiksa')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('indication')
                    ->label('Indikasi')
                    ->getStateUsing(fn (NetworkQualityMeasurement $record): string => NetworkQualityAdvice::forMeasurement($record)['summary'])
                    ->wrap()->limit(95)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('probe_type')
                    ->label('Metode')
                    ->options(['icmp' => 'Ping', 'dns' => 'DNS', 'tcp' => 'TCP', 'https' => 'HTTPS']),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $id = $data['value'] ?? null;

                        return $query->when($id, fn (Builder $q): Builder => $q->whereHas(
                            'device', fn (Builder $d): Builder => $d->where('owner_unit_id', $id),
                        ));
                    }),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->actions([
                Tables\Actions\Action::make('recommendation')
                    ->label('Rekomendasi')
                    ->icon('heroicon-o-light-bulb')
                    ->modalHeading(fn (NetworkQualityMeasurement $record): string => 'Rekomendasi: '.$record->target_name)
                    ->modalContent(fn (NetworkQualityMeasurement $record): View => view('filament.pages.modals.network-quality-recommendation', [
                        'advice' => NetworkQualityAdvice::forMeasurement($record),
                    ]))
                    ->modalWidth('xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\Action::make('recheck')
                    ->label('Uji Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Jadwalkan pengukuran ulang?')
                    ->modalDescription('Pengujian dijalankan melalui queue network-quality, tanpa mengubah perangkat.')
                    ->modalSubmitActionLabel('Jalankan Uji')
                    ->visible(fn (NetworkQualityMeasurement $record): bool => (auth()->user()?->can('update_device') ?? false)
                        && (
                            ($record->device_id !== null && (bool) $record->device?->monitoring_enabled)
                            || ($record->target_id !== null && (bool) $record->target?->enabled)
                        ))
                    ->action(function (NetworkQualityMeasurement $record): void {
                        abort_unless(
                            config('network_quality.enabled', false)
                                && (auth()->user()?->can('update_device') ?? false),
                            403,
                        );

                        $cacheKey = 'network-quality:manual:' . sha1($record->monitor_key);

                        if (! Cache::add($cacheKey, now()->timestamp, 60)) {
                            Notification::make()
                                ->title('Pengukuran baru saja diminta')
                                ->body('Coba kembali setelah satu menit.')
                                ->warning()
                                ->send();

                            return;
                        }

                        RunNetworkQuality::dispatch($record->device_id, $record->target_id)
                            ->onQueue('network-quality');

                        Notification::make()
                            ->title('Pengukuran ulang diantrikan')
                            ->body('Hasil akan muncul setelah worker selesai memproses.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('device')
                    ->label('Buka Perangkat')
                    ->icon('heroicon-o-computer-desktop')
                    ->url(fn (NetworkQualityMeasurement $record): string => DeviceResource::getUrl('edit', [
                        'record' => $record->device_id,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(fn (NetworkQualityMeasurement $record): bool => $record->device_id !== null
                        && (auth()->user()?->can('update_device') ?? false)),
                Tables\Actions\Action::make('target')
                    ->label('Target Layanan')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(fn (): string => NetworkQualityTargetResource::getUrl('index'))
                    ->openUrlInNewTab()
                    ->visible(fn (NetworkQualityMeasurement $record): bool => $record->target_id !== null
                        && NetworkQualityTargetResource::canViewAny()),
            ])
            ->emptyStateHeading('Belum ada pengukuran pada kategori ini')
            ->emptyStateDescription('Periksa interval pengambilan data, worker, dan status target. Hasil lama berada pada histori halaman utama.')
            ->emptyStateIcon('heroicon-o-signal');
    }

    public function render(): View
    {
        $this->authorizeViewing();

        return view('livewire.network-quality-detail-table');
    }
}
