<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Network;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\NetworkResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\NetworkResource\RelationManagers;

class NetworkResource extends Resource
{
    protected static ?string $model = Network::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make([
                    Forms\Components\TextInput::make('address')
                    ->required()
                    ->label('IP Address')
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $netmask_original = $get('netmask_original');
                        $address = $state;
                        if ($netmask_original && $address) {
                            // Convert netmask prefix length to an IP address format
                            $netmask = long2ip((1 << 32) - (1 << (32 - $netmask_original)));
                            // Konversi alamat IP dan netmask ke dalam bentuk numerik
                            $ipNumeric = ip2long($address);
                            $netmaskNumeric = ip2long($netmask);

                            // Check if ip2long conversion was successful
                            if ($ipNumeric === false || $netmaskNumeric === false) {
                                die("Invalid IP address or netmask");
                            }
                            // Hitung network address
                            $networkAddress = $ipNumeric & $netmaskNumeric;

                            // Hitung network address
                            $networkAddress = $ipNumeric & $netmaskNumeric;

                            // Hitung bitwise NOT dari netmaskNumeric
                            $bitwiseNotNetmask = ~$netmaskNumeric;

                            // Hitung broadcast address
                            $broadcastAddress = $networkAddress | (~$netmaskNumeric & 0xFFFFFFFF);

                            // Hitung host minimum dan host maximum
                            $hostMin = $networkAddress + 1;
                            $hostMax = $broadcastAddress - 1;

                            // Hitung jumlah host dalam network
                            $hostsPerNet = abs($hostMax - $hostMin) + 1;

                            // Konversi hasil ke dalam format IP
                            $networkAddressIP = long2ip($networkAddress);
                            $broadcastAddressIP = long2ip($broadcastAddress);
                            $hostMinIP = long2ip($hostMin);
                            $hostMaxIP = long2ip($hostMax);


                            $set('address', $address);
                            $set('netmask', $netmask);
                            $set('network', $networkAddressIP);
                            $set('broadcast', $broadcastAddressIP);
                            $set('hostMin', $hostMinIP);
                            $set('hostMax', $hostMaxIP);
                            $set('hostsPerNet', $hostsPerNet);
                        }
                    })
                    ->live(onBlur:true)
                    ->ipv4(),
                Forms\Components\TextInput::make('netmask_original')
                    ->required()
                    ->label('Netmask')
                    ->hint('Misalnya 24, 22, dsb')
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        $address = $get('address');
                        $netmask_original = $state;
                        if ($netmask_original && $address) {
                            // Convert netmask prefix length to an IP address format
                            $netmask = long2ip((1 << 32) - (1 << (32 - $netmask_original)));
                            // Konversi alamat IP dan netmask ke dalam bentuk numerik
                            $ipNumeric = ip2long($address);
                            $netmaskNumeric = ip2long($netmask);

                            // Check if ip2long conversion was successful
                            if ($ipNumeric === false || $netmaskNumeric === false) {
                                //die("Invalid IP address or netmask");
                                Notification::make()
                                    ->title('IP Address atau Netmask salah')
                                    ->danger();
                            }
                            // Hitung network address
                            $networkAddress = $ipNumeric & $netmaskNumeric;

                            // Hitung network address
                            $networkAddress = $ipNumeric & $netmaskNumeric;

                            // Hitung bitwise NOT dari netmaskNumeric
                            $bitwiseNotNetmask = ~$netmaskNumeric;

                            // Hitung broadcast address
                            $broadcastAddress = $networkAddress | (~$netmaskNumeric & 0xFFFFFFFF);

                            // Hitung host minimum dan host maximum
                            $hostMin = $networkAddress + 1;
                            $hostMax = $broadcastAddress - 1;

                            // Hitung jumlah host dalam network
                            $hostsPerNet = abs($hostMax - $hostMin) + 1;

                            // Konversi hasil ke dalam format IP
                            $networkAddressIP = long2ip($networkAddress);
                            $broadcastAddressIP = long2ip($broadcastAddress);
                            $hostMinIP = long2ip($hostMin);
                            $hostMaxIP = long2ip($hostMax);


                            $set('address', $address);
                            $set('netmask', $netmask);
                            $set('network', $networkAddressIP);
                            $set('broadcast', $broadcastAddressIP);
                            $set('hostMin', $hostMinIP);
                            $set('hostMax', $hostMaxIP);
                            $set('hostPerNet', $hostsPerNet);
                        }
                    })
                    ->live(onBlur:true)
                    ->prefix("/")
                    ->integer(),
                ]),

                Forms\Components\TextInput::make('netmask')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),    
                Forms\Components\TextInput::make('network')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),
                Forms\Components\TextInput::make('broadcast')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),
                Forms\Components\TextInput::make('hostMin')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),
                Forms\Components\TextInput::make('hostMax')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),
                Forms\Components\TextInput::make('hostPerNet')
                    ->required()
                    ->readOnly()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('netmask')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('network')
                    ->searchable(),
                Tables\Columns\TextColumn::make('broadcast')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostMin')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostMax')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostPerNet')
                    ->searchable(),
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
            'index' => Pages\ListNetworks::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit' => Pages\EditNetwork::route('/{record}/edit'),
        ];
    }
}
