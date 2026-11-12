<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QualityMeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'qualityMeasurements';
    protected static ?string $title = 'Kualitas Jaringan';

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('checked_at')
            ->columns([
                Tables\Columns\TextColumn::make('checked_at')->label('Waktu')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('quality')->label('Kualitas')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'good' => 'success', 'degraded' => 'warning', 'bad' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latency_ms')->label('RTT')->suffix(' ms')->placeholder('-'),
                Tables\Columns\TextColumn::make('packet_loss_pct')->label('Loss')->suffix('%')->placeholder('-'),
                Tables\Columns\TextColumn::make('rtt_variation_ms')->label('Variasi RTT')->suffix(' ms')->placeholder('-'),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
