<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceMovementResource\Pages;
use App\Models\DeviceMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeviceMovementResource extends Resource
{
    protected static ?string $model = DeviceMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Mutasi Perangkat';
    protected static ?string $modelLabel = 'Mutasi Perangkat';
    protected static ?string $pluralModelLabel = 'Mutasi Perangkat';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('moved_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DeviceMovement $record): ?string => $record->device?->asset_code),
                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'initial_assignment' ? 'Penempatan Awal' : 'Mutasi'),
                Tables\Columns\TextColumn::make('fromRoom.nama')
                    ->label('Dari')
                    ->placeholder('Belum ada lokasi'),
                Tables\Columns\TextColumn::make('toRoom.nama')
                    ->label('Ke')
                    ->placeholder('Tanpa lokasi'),
                Tables\Columns\TextColumn::make('changedBy.name')
                    ->label('Diubah Oleh')
                    ->placeholder('Sistem'),
                Tables\Columns\IconColumn::make('automatic')
                    ->label('Otomatis')
                    ->boolean(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->placeholder('-')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Jenis')
                    ->options([
                        'initial_assignment' => 'Penempatan Awal',
                        'relocation' => 'Mutasi',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('moved_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceMovements::route('/'),
        ];
    }
}
