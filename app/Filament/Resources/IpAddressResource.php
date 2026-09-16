<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpAddressResource\Pages;
use App\Models\IpAddress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $modelLabel = 'IP Address';
    protected static ?string $navigationLabel = 'IP Address';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('network_id')
                    ->relationship('network', 'address')
                    ->label('Jaringan')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('nama')
                    ->label('Alamat IPv4')
                    ->required()
                    ->ipv4()
                    ->maxLength(45),
                Forms\Components\Toggle::make('status')
                    ->label('Online')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Status diperbarui otomatis melalui proses ping.'),
                Forms\Components\Toggle::make('booked')
                    ->label('Terpakai')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Status terpakai disinkronkan otomatis dari perangkat yang menggunakan IP ini.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Alamat')
                    ->searchable(),
                Tables\Columns\TextColumn::make('network.address')
                    ->label('Jaringan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('PING')
                    ->boolean(),
                Tables\Columns\IconColumn::make('booked')
                    ->label('Terpakai')
                    ->boolean(),
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
            ->bulkActions([
                ExportBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIpAddresses::route('/'),
            'create' => Pages\CreateIpAddress::route('/create'),
            'edit' => Pages\EditIpAddress::route('/{record}/edit'),
        ];
    }
}
