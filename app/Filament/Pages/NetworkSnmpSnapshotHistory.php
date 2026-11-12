<?php

namespace App\Filament\Pages;

use App\Models\NetworkSnmpSnapshot;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class NetworkSnmpSnapshotHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $title = 'Riwayat Snapshot SNMP Perangkat';
    protected static string $view = 'filament.pages.network-snmp-snapshot-history';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return NetworkSnmpData::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkSnmpSnapshot::query()->with('device'))
            ->columns([
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')->searchable(),
                Tables\Columns\TextColumn::make('snmp_version')
                    ->label('Versi')->badge(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Mulai')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Selesai')->dateTime('d/m/Y H:i:s')->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'running' => 'info',
                        'partial' => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('oid_count')
                    ->label('OID')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('limited_values')
                    ->label('Nilai Terbatas')->numeric(),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('Diagnostik')->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_id')
                    ->label('Perangkat')->relationship('device', 'nama')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')->options([
                        'running' => 'Mengambil data',
                        'complete' => 'Lengkap',
                        'partial' => 'Sebagian',
                        'failed' => 'Gagal',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Lihat OID')->icon('heroicon-o-eye')
                    ->modalHeading(fn (NetworkSnmpSnapshot $record): string =>
                        'Snapshot: '.$record->device?->nama)
                    ->modalContent(fn (NetworkSnmpSnapshot $record): View => view(
                        'filament.pages.modals.network-snmp-oid-list', ['snapshot' => $record]
                    ))
                    ->modalWidth('7xl')->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada riwayat pengambilan SNMP');
    }
}
