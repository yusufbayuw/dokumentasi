<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Riwayat Mutasi Lokasi';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('moved_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('moved_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
