<?php

namespace App\Filament\Resources\UnitResource\RelationManagers;

use App\Filament\Resources\RuanganResource;
use App\Models\Ruangan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RuangansRelationManager extends RelationManager
{
    protected static string $relationship = 'ruangan';

    protected static ?string $title = 'Ruangan';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Ruangan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lantai.nama')
                    ->label('Lantai')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('lantai.gedung.nama')
                    ->label('Gedung')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('device_count')
                    ->counts('device')
                    ->label('Perangkat')
                    ->badge(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Ruangan $record): string => RuanganResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('nama');
    }
}
