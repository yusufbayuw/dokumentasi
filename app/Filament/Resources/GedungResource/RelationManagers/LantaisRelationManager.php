<?php

namespace App\Filament\Resources\GedungResource\RelationManagers;

use App\Filament\Resources\LantaiResource;
use App\Models\Lantai;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LantaisRelationManager extends RelationManager
{
    protected static string $relationship = 'lantai';

    protected static ?string $title = 'Lantai';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Lantai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ruangan_count')
                    ->counts('ruangan')
                    ->label('Ruangan')
                    ->badge(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Denah')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Lantai $record): string => LantaiResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('nama');
    }
}
