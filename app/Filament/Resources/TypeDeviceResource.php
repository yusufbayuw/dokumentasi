<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TypeDeviceResource\Pages;
use App\Models\TypeDevice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TypeDeviceResource extends Resource
{
    protected static ?string $model = TypeDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';
    protected static ?string $navigationGroup = 'Konfigurasi';
    protected static ?string $navigationLabel = 'Tipe Perangkat';
    protected static ?string $modelLabel = 'Tipe Perangkat';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'tipe-perangkat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('kode')
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('device_count')
                    ->counts('device')
                    ->label('Perangkat')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTypeDevices::route('/'),
            'create' => Pages\CreateTypeDevice::route('/create'),
            'edit' => Pages\EditTypeDevice::route('/{record}/edit'),
        ];
    }
}
