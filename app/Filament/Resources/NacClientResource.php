<?php

namespace App\Filament\Resources;

use App\Models\NacClient;
use App\Filament\Resources\NacClientResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NacClientResource extends Resource
{
    protected static ?string $model = NacClient::class;
    protected static ?string $navigationGroup = 'Network Access Control';
    protected static ?string $navigationLabel = 'Network Clients';
    protected static ?string $modelLabel = 'Network Clients';
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

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
            Forms\Components\Select::make('user_id')->label('Pemilik')->relationship('user', 'name')->required()->searchable()->preload(),
            Forms\Components\Select::make('cmdb_device_id')->label('Aset CMDB (opsional)')->relationship('cmdbDevice', 'nama')->searchable()->preload(),
            Forms\Components\TextInput::make('label')->label('Nama Perangkat')->required()->maxLength(255),
            Forms\Components\TextInput::make('platform')->label('Platform')->maxLength(255),
            Forms\Components\Select::make('status')->options(['pending'=>'Pending','active'=>'Active','revoked'=>'Revoked'])->required()->default('pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('label')->searchable(),
            Tables\Columns\TextColumn::make('user.name')->label('Pemilik')->searchable(),
            Tables\Columns\TextColumn::make('cmdbDevice.nama')->label('CMDB')->placeholder('-'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('platform')->placeholder('-'),
        ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNacClients::route('/')];
    }
}
