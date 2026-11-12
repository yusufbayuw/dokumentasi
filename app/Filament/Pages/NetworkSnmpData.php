<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeviceResource;
use App\Jobs\StartDeviceSnmpSnapshot;
use App\Models\NetworkSnmpProfile;
use App\Models\NetworkSnmpSnapshot;
use App\NetworkMonitoring\SnmpProtocolPolicy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class NetworkSnmpData extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Data SNMP Perangkat';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Data Mentah SNMP per Perangkat';
    protected static string $view = 'filament.pages.network-snmp-data';

    public static function canAccess(): bool
    {
        // Raw OIDs may expose IP/MAC tables, usernames and other sensitive
        // device metadata; require operator privilege, not just read access.
        return (bool) config('network_quality.enabled', false)
            && (bool) config('network_quality.snmp.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false)
            && (auth()->user()?->can('update_device') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkSnmpProfile::query()->with(['device.ownerUnit', 'latestSnapshot']))
            ->columns([
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('device.ownerUnit.nama')
                    ->label('Unit')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Versi')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === '2c' ? 'SNMPv2c' : 'SNMPv3'),
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Polling Aktif')->boolean(),
                Tables\Columns\TextColumn::make('capture_status')
                    ->label('Snapshot')
                    ->badge()
                    ->state(fn (NetworkSnmpProfile $record): string =>
                        $record->latestSnapshot?->status ?? 'not_started')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'running' => 'Mengambil data',
                        'complete' => 'Lengkap',
                        'partial' => 'Sebagian',
                        'failed' => 'Gagal',
                        default => 'Belum diambil',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success', 'running' => 'info',
                        'partial' => 'warning', 'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('oid_total')
                    ->label('Jumlah OID')
                    ->state(fn (NetworkSnmpProfile $record): string =>
                        number_format((int) ($record->latestSnapshot?->oid_count ?? 0)))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('snapshot_time')
                    ->label('Pengambilan Terakhir')
                    ->state(fn (NetworkSnmpProfile $record): ?string =>
                        $record->latestSnapshot?->started_at?->format('d/m/Y H:i:s'))
                    ->placeholder('Belum ada'),
                Tables\Columns\TextColumn::make('last_poll_error')
                    ->label('Diagnostik')
                    ->placeholder('Tidak ada')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('capture')
                    ->label('Ambil Semua OID')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Data SNMP mentah akan diambil melalui antrean secara bertahap. Tidak ada perubahan konfigurasi pada perangkat.')
                    ->visible(fn (NetworkSnmpProfile $record): bool =>
                        SnmpProtocolPolicy::canPoll($record)
                        && (bool) $record->device?->monitoring_enabled)
                    ->action(function (NetworkSnmpProfile $record): void {
                        abort_unless(static::canAccess(), 403);
                        if (! SnmpProtocolPolicy::canPoll($record)
                            || ! $record->device?->monitoring_enabled) {
                            Notification::make()->title('Polling perangkat belum aktif')->warning()->send();
                            return;
                        }

                        $key = 'network-quality:snmp:manual:snapshot:'.$record->device_id;
                        if (! Cache::add($key, now()->timestamp, 60)) {
                            Notification::make()->title('Pengambilan baru saja diminta')->warning()->send();
                            return;
                        }

                        StartDeviceSnmpSnapshot::dispatch($record->device_id)
                            ->onQueue('network-quality');

                        Notification::make()->title('Pengambilan OID diantrikan')->success()->send();
                    }),
                Tables\Actions\Action::make('oids')
                    ->label('Lihat OID')
                    ->icon('heroicon-o-table-cells')
                    ->modalHeading(fn (NetworkSnmpProfile $record): string =>
                        'Data SNMP: '.$record->device?->nama)
                    ->modalContent(fn (NetworkSnmpProfile $record): View => view(
                        'filament.pages.modals.network-snmp-oid-list',
                        ['snapshot' => $record->latestSnapshot]
                    ))
                    ->modalSubmitAction(false)->modalWidth('7xl')
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (NetworkSnmpProfile $record): bool =>
                        $record->latestSnapshot?->oid_count > 0),
                Tables\Actions\Action::make('device')
                    ->label('Edit Perangkat')
                    ->icon('heroicon-o-computer-desktop')
                    ->url(fn (NetworkSnmpProfile $record): string =>
                        DeviceResource::getUrl('edit', ['record' => $record->device_id]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada perangkat dengan profil SNMP')
            ->emptyStateDescription('Daftarkan SNMP di Perangkat > Edit > Monitoring SNMP. Snapshot tidak memerlukan data interface CMDB.');
    }
}
