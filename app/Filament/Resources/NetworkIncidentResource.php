<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkIncidentResource\Pages;
use App\Models\NetworkIncident;
use App\Services\NetworkIncidentWorkflow;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class NetworkIncidentResource extends Resource
{
    protected static ?string $model = NetworkIncident::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Insiden Jaringan';
    protected static ?string $modelLabel = 'Insiden Jaringan';
    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.incidents.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Target')->searchable()->wrap()
                    ->description(fn (NetworkIncident $record): string => $record->monitor_key),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unreachable' => 'Tidak terjangkau',
                        'collector' => 'Gagal monitor',
                        default => 'Kualitas menurun',
                    }),
                Tables\Columns\TextColumn::make('severity')
                    ->label('Prioritas')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'critical' ? 'Kritis' : 'Peringatan')
                    ->color(fn (string $state): string => $state === 'critical' ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Terbuka',
                        'acknowledged' => 'Ditangani',
                        default => 'Selesai',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger', 'acknowledged' => 'warning', default => 'success',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Mulai')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Terakhir Terukur')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('device.ownerUnit.nama')
                    ->label('Unit')->placeholder('-')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')->options([
                        'open' => 'Terbuka',
                        'acknowledged' => 'Ditangani',
                        'resolved' => 'Selesai',
                    ]),
                Tables\Filters\SelectFilter::make('severity')
                    ->label('Prioritas')->options(['critical' => 'Kritis', 'warning' => 'Peringatan']),
                Tables\Filters\SelectFilter::make('kind')
                    ->label('Jenis')->options([
                        'unreachable' => 'Tidak terjangkau',
                        'quality' => 'Kualitas menurun',
                        'collector' => 'Gagal monitor',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('diagnosis')
                    ->label('Analisis & Riwayat')
                    ->icon('heroicon-o-light-bulb')
                    ->modalHeading(fn (NetworkIncident $record): string => 'Analisis: '.$record->target_name)
                    ->modalContent(fn (NetworkIncident $record): View => view('filament.pages.modals.network-incident-diagnosis', [
                        'incident' => $record,
                        'events' => $record->events()->orderByDesc('created_at')->limit(30)->get(),
                    ]))
                    ->modalSubmitAction(false)->modalWidth('3xl')
                    ->modalCancelActionLabel('Tutup'),
                Tables\Actions\Action::make('acknowledge')
                    ->label('Terima Penanganan')
                    ->icon('heroicon-o-hand-raised')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (NetworkIncident $record): bool => $record->status === 'open'
                        && (auth()->user()?->can('update_device') ?? false))
                    ->action(function (NetworkIncident $record): void {
                        abort_unless(static::canViewAny()
                            && (auth()->user()?->can('update_device') ?? false), 403);
                        app(NetworkIncidentWorkflow::class)->acknowledge($record->id, (int) auth()->id());
                    }),
                Tables\Actions\Action::make('resolve')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (NetworkIncident $record): bool => $record->status !== 'resolved'
                        && (auth()->user()?->can('update_device') ?? false))
                    ->action(function (NetworkIncident $record): void {
                        abort_unless(static::canViewAny()
                            && (auth()->user()?->can('update_device') ?? false), 403);
                        app(NetworkIncidentWorkflow::class)->resolve($record->id, (int) auth()->id());
                    }),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada indikasi gangguan berulang')
            ->emptyStateDescription('Insiden hanya muncul setelah sejumlah sampel berturut-turut memenuhi aturan deteksi.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNetworkIncidents::route('/')];
    }
}
