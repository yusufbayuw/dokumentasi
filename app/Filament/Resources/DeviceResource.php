<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Device;
use Filament\Forms\Form;
use App\Models\IpAddress;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\DeviceResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Forms\Components\LeafletMap;
use App\Models\Lantai;
use App\Models\Ruangan;
use Filament\Forms\Get;
use Rats\Zkteco\Lib\ZKTeco;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $modelLabel = 'Perangkat';
    protected static ?string $navigationLabel = 'Perangkat';
    protected static ?int $navigationSort = 2;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\Select::make('ip_address_id')
                        ->relationship('ip', 'nama')
                        ->label('IP Address')
                        ->searchable(),
                    Forms\Components\Select::make('type_device_id')
                        ->relationship('type', 'nama')
                        ->label('Jenis Perangkat')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('ruangan_id')
                        ->relationship('ruangan', 'nama')
                        ->label('Nama Ruangan')
                        ->reactive()
                        ->preload()
                        ->searchable(),
                    Forms\Components\TextInput::make('nama')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('keterangan')
                        ->maxLength(255),
                    LeafletMap::make('location')
                        ->label('Lokasi Perangkat')
                        ->hidden(fn(Get $get) => !$get('ruangan_id'))
                        ->floorImage(
                            function (Get $get) {
                                return Ruangan::find($get('ruangan_id'))->lantai->image_path ?? null;
                            }
                        ),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip.nama')
                    ->label('IP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type.nama')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ruangan.nama')
                    ->label('Lokasi Ruang')
                    ->sortable(),
                Tables\Columns\IconColumn::make('ip.status')
                    ->sortable()
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
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('restart')
                    ->label('Reboot')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Device $device) {
                        $ip = $device->ip->nama;
                        $zk = new ZKTeco($ip);
                        $zk->connect();
                        $zk->restart();
                        //$zk->disconnect();
                    })
                    ->hidden(fn(Device $device) => ($device->type->nama != "Finger Print")),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
