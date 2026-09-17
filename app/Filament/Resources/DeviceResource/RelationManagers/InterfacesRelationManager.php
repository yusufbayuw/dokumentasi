<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InterfacesRelationManager extends RelationManager
{
    protected static string $relationship = 'interfaces';

    protected static ?string $title = 'Interface Jaringan';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama Interface')
                ->placeholder('Contoh: Gi1/0/24, eth0, SFP1')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('interface_type')
                ->label('Tipe')
                ->options([
                    'ethernet' => 'Ethernet',
                    'fiber' => 'Fiber',
                    'wireless' => 'Wireless',
                    'virtual' => 'Virtual',
                    'loopback' => 'Loopback',
                    'other' => 'Lainnya',
                ])
                ->default('ethernet')
                ->required(),
            Forms\Components\TextInput::make('mac_address')
                ->label('MAC Address')
                ->placeholder('AA:BB:CC:DD:EE:FF')
                ->maxLength(32)
                ->regex('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'),
            Forms\Components\Select::make('ip_address_id')
                ->relationship('ipAddress', 'nama')
                ->label('IP Interface')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('speed_mbps')
                ->label('Speed (Mbps)')
                ->numeric()
                ->minValue(1),
            Forms\Components\TextInput::make('vlan')
                ->label('VLAN')
                ->maxLength(255),
            Forms\Components\Select::make('admin_status')
                ->label('Admin Status')
                ->options(['up' => 'Up', 'down' => 'Down'])
                ->default('up')
                ->required(),
            Forms\Components\Select::make('oper_status')
                ->label('Operational Status')
                ->options([
                    'up' => 'Up',
                    'down' => 'Down',
                    'unknown' => 'Unknown',
                ])
                ->default('unknown')
                ->required(),
            Forms\Components\Toggle::make('is_management')
                ->label('Interface Management')
                ->helperText('Gunakan untuk interface utama yang dipakai mengelola perangkat.'),
            Forms\Components\Textarea::make('description')
                ->label('Keterangan')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Interface')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('interface_type')
                    ->label('Tipe')
                    ->badge(),
                Tables\Columns\TextColumn::make('ipAddress.nama')
                    ->label('IP')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('mac_address')
                    ->label('MAC')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vlan')
                    ->label('VLAN')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('speed_mbps')
                    ->label('Speed')
                    ->formatStateUsing(fn ($state): string => $state ? number_format((int) $state) . ' Mbps' : '-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('oper_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success',
                        'down' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_management')
                    ->label('Mgmt')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->defaultSort('is_management', 'desc');
    }
}
