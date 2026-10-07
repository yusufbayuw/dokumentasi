<?php

namespace App\Filament\Resources\TypeDeviceResource\RelationManagers;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Services\DeviceHealthService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'device';

    protected static ?string $title = 'Perangkat';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Perangkat')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Device $record): ?string => $record->hostname),
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Kode Aset')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('management_ip')
                    ->label('Management IP')
                    ->state(fn (Device $record): ?string => $record->ip?->nama
                        ?? $record->managementInterface?->ipAddress?->nama)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ruangan.nama')
                    ->label('Ruangan')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('health_status')
                    ->label('Health')
                    ->state(fn (Device $record): string => app(DeviceHealthService::class)->status($record))
                    ->formatStateUsing(fn (string $state): string => app(DeviceHealthService::class)->label($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        'stale' => 'warning',
                        'disabled' => 'gray',
                        default => 'info',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Device $record): string => DeviceResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('nama');
    }
}
