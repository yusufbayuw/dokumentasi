<?php

namespace App\Filament\Resources;

use App\Models\NacNetwork;
use App\Filament\Resources\NacNetworkResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NacNetworkResource extends Resource
{
    protected static ?string $model = NacNetwork::class;
    protected static ?string $navigationGroup = 'Network Access Control';
    protected static ?string $navigationLabel = 'Wi-Fi Networks';
    protected static ?string $modelLabel = 'Wi-Fi Networks';
    protected static ?string $navigationIcon = 'heroicon-o-wifi';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Forms\Components\TextInput::make('ssid')->label('SSID')->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('enabled')->label('Aktif')->default(false)->helperText('Nonaktif sampai integrasi RADIUS dan EAP-TLS tervalidasi.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('ssid')->searchable(),
            Tables\Columns\IconColumn::make('enabled')->label('Aktif')->boolean(),
        ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNacNetworks::route('/')];
    }
}
