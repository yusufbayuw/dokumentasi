<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\NetworkInterfaceSample;
use App\Models\Unit;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NetworkInterfaces extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Traffic Interface (historis)';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Traffic & Error Interface (SNMPv3 / v2c)';
    protected static string $view = 'filament.pages.network-interfaces';

    // Per-device full OID snapshots are now the primary SNMP collection.
    // Keep this historical derived-metrics page accessible to existing links.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.snmp.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    public function table(Table $table): Table
    {
        $latestIds = DB::table('network_interface_samples')
            ->selectRaw('MAX(id)')
            ->whereNotNull('device_interface_id')
            ->where('checked_at', '>=', now()->subMinutes(30))
            ->groupBy('device_interface_id');

        return $table
            ->query(NetworkInterfaceSample::query()
                ->whereIn('id', $latestIds)
                ->with(['device.ownerUnit', 'deviceInterface', 'device.snmpProfile']))
            ->columns([
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('snmp_version')
                    ->label('Versi SNMP')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        $state === '2c' ? 'v2c' : 'v3')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('interface_name')
                    ->label('Interface')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('device.ownerUnit.nama')
                    ->label('Unit')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Port')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'up' => 'Up', 'down' => 'Down', default => 'Tidak diketahui',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success', 'down' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rx_mbps')
                    ->label('Terima (RX)')->suffix(' Mbps')->placeholder('Butuh 2 sampel')->sortable(),
                Tables\Columns\TextColumn::make('tx_mbps')
                    ->label('Kirim (TX)')->suffix(' Mbps')->placeholder('Butuh 2 sampel')->sortable(),
                Tables\Columns\TextColumn::make('utilization')
                    ->label('Utilisasi RX')
                    ->state(fn (NetworkInterfaceSample $record): ?float => $record->speed_mbps && $record->rx_mbps !== null
                        ? round((float) $record->rx_mbps / $record->speed_mbps * 100, 1)
                        : null)
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : $state.'%')
                    ->color(fn ($state): string => $state !== null && $state >= 90 ? 'danger'
                        : ($state !== null && $state >= 75 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('in_errors_delta')
                    ->label('Error RX')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('out_errors_delta')
                    ->label('Error TX')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Diperiksa')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('Kode Error')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Port')->options(['up' => 'Up', 'down' => 'Down', 'unknown' => 'Tidak diketahui']),
                Tables\Filters\SelectFilter::make('snmp_version')
                    ->label('Versi SNMP')
                    ->options(['3' => 'SNMPv3', '2c' => 'SNMPv2c']),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $value) => $q->whereHas('device', fn (Builder $d) => $d->where('owner_unit_id', $value))
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('history')
                    ->label('Riwayat')->icon('heroicon-o-chart-bar')
                    ->modalHeading(fn (NetworkInterfaceSample $record): string => 'Traffic: '.$record->device?->nama.' / '.$record->interface_name)
                    ->modalContent(fn (NetworkInterfaceSample $record): View => view('filament.pages.modals.interface-history', [
                        'interfaceId' => $record->device_interface_id,
                    ]))
                    ->modalSubmitAction(false)->modalWidth('7xl')
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\Action::make('device')
                    ->label('Buka Perangkat')->icon('heroicon-o-computer-desktop')
                    ->url(fn (NetworkInterfaceSample $record): string => DeviceResource::getUrl('edit', ['record' => $record->device_id]))
                    ->openUrlInNewTab()
                    ->visible(fn (NetworkInterfaceSample $record): bool => $record->device_id !== null
                        && (auth()->user()?->can('update_device') ?? false)),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada hasil interface')
            ->emptyStateDescription('Metrik interface terolah merupakan tampilan historis. Data SNMP lengkap sekarang tersedia di Infrastruktur > Data SNMP Perangkat.');
    }
}
