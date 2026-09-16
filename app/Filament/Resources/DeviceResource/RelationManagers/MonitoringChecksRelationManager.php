<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MonitoringChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'monitoringChecks';

    protected static ?string $title = 'Riwayat Monitoring';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('checked_at')
            ->columns([
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'online' ? 'Online' : 'Offline')
                    ->color(fn (string $state): string => $state === 'online' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->suffix(' ms')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_type')
                    ->label('Metode')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('message')
                    ->label('Pesan')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
