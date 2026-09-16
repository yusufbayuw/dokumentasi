<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkResource\Pages;
use App\Models\Network;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class NetworkResource extends Resource
{
    protected static ?string $model = Network::class;

    protected static ?string $navigationIcon = 'heroicon-o-wifi';
    protected static ?string $modelLabel = 'Jaringan';
    protected static ?string $navigationGroup = 'Konfigurasi';
    protected static ?string $navigationLabel = 'Jaringan';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'jaringan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Konfigurasi Jaringan')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->required()
                            ->label('IP Address')
                            ->ipv4()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateNetwork($get, $set)),

                        Forms\Components\TextInput::make('netmask_original')
                            ->required()
                            ->label('Prefix / CIDR')
                            ->hint('Rentang yang dikelola dibatasi /16 sampai /30 agar pool IP tetap aman.')
                            ->prefix('/')
                            ->integer()
                            ->minValue(16)
                            ->maxValue(30)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateNetwork($get, $set)),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Hasil Perhitungan')
                    ->schema([
                        Forms\Components\TextInput::make('netmask')
                            ->label('Netmask')
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('network')
                            ->label('Network Address')
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('broadcast')
                            ->label('Broadcast')
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('hostMin')
                            ->label('Host Pertama')
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('hostMax')
                            ->label('Host Terakhir')
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('hostPerNet')
                            ->label('Jumlah Host')
                            ->required()
                            ->readOnly(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->label('IP Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('netmask_original')
                    ->label('CIDR')
                    ->formatStateUsing(fn ($state) => '/'.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('netmask')
                    ->label('Netmask')
                    ->searchable(),
                Tables\Columns\TextColumn::make('network')
                    ->label('Network')
                    ->searchable(),
                Tables\Columns\TextColumn::make('broadcast')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostMin')
                    ->label('Host Min')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostMax')
                    ->label('Host Max')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostPerNet')
                    ->label('Host')
                    ->numeric()
                    ->sortable(),
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
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNetworks::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit' => Pages\EditNetwork::route('/{record}/edit'),
        ];
    }

    private static function calculateNetwork(Get $get, Set $set): void
    {
        $address = $get('address');
        $prefix = $get('netmask_original');

        if (blank($address) || blank($prefix)) {
            return;
        }

        $prefix = (int) $prefix;
        $rawIp = ip2long((string) $address);

        if ($rawIp === false || $prefix < 16 || $prefix > 30) {
            return;
        }

        $ipLong = (int) sprintf('%u', $rawIp);
        $netmaskLong = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
        $networkLong = $ipLong & $netmaskLong;
        $broadcastLong = $networkLong | (~$netmaskLong & 0xFFFFFFFF);
        $hostMinLong = $networkLong + 1;
        $hostMaxLong = $broadcastLong - 1;
        $hostsPerNet = max(0, ($hostMaxLong - $hostMinLong) + 1);

        $set('netmask', long2ip($netmaskLong));
        $set('network', long2ip($networkLong));
        $set('broadcast', long2ip($broadcastLong));
        $set('hostMin', long2ip($hostMinLong));
        $set('hostMax', long2ip($hostMaxLong));
        $set('hostPerNet', $hostsPerNet);
    }
}
