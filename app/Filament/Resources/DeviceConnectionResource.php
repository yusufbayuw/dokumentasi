<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceConnectionResource\Pages;
use App\Models\DeviceConnection;
use App\Models\DeviceInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeviceConnectionResource extends Resource
{
    protected static ?string $model = DeviceConnection::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Koneksi Jaringan';
    protected static ?string $modelLabel = 'Koneksi Jaringan';
    protected static ?string $pluralModelLabel = 'Koneksi Jaringan';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Relasi Perangkat')
                    ->description('Koneksi manual melengkapi relasi yang berasal dari Topologi. Relasi otomatis dari Topologi hanya dapat diubah dari menu Topologi.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('source_device_id')
                            ->relationship('sourceDevice', 'nama')
                            ->label('Perangkat Sumber / Upstream')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('source_interface_id', null))
                            ->required()
                            ->rules(['different:target_device_id']),
                        Forms\Components\Select::make('source_interface_id')
                            ->label('Interface Sumber')
                            ->options(fn (Get $get): array => DeviceInterface::query()
                                ->where('device_id', $get('source_device_id'))
                                ->orderByDesc('is_management')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->disabled(fn (Get $get): bool => ! filled($get('source_device_id')))
                            ->helperText('Opsional. Pilih interface terstruktur jika sudah tersedia.'),
                        Forms\Components\Select::make('target_device_id')
                            ->relationship('targetDevice', 'nama')
                            ->label('Perangkat Tujuan / Downstream')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('target_interface_id', null))
                            ->required()
                            ->rules(['different:source_device_id']),
                        Forms\Components\Select::make('target_interface_id')
                            ->label('Interface Tujuan')
                            ->options(fn (Get $get): array => DeviceInterface::query()
                                ->where('device_id', $get('target_device_id'))
                                ->orderByDesc('is_management')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->disabled(fn (Get $get): bool => ! filled($get('target_device_id')))
                            ->helperText('Opsional. Pilih interface terstruktur jika sudah tersedia.'),
                        Forms\Components\Hidden::make('source')
                            ->default('manual'),
                        Forms\Components\Select::make('connection_type')
                            ->label('Jenis Koneksi')
                            ->options([
                                'ethernet' => 'Ethernet',
                                'fiber' => 'Fiber',
                                'wireless' => 'Wireless',
                                'vpn' => 'VPN',
                                'logical' => 'Logical',
                                'other' => 'Lainnya',
                            ])
                            ->default('ethernet')
                            ->required(),
                        Forms\Components\TextInput::make('source_port')
                            ->label('Port Sumber (Legacy / Fallback)')
                            ->helperText('Dipertahankan untuk koneksi lama. Kosongkan bila sudah memilih Interface Sumber.')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('target_port')
                            ->label('Port Tujuan (Legacy / Fallback)')
                            ->helperText('Dipertahankan untuk koneksi lama. Kosongkan bila sudah memilih Interface Tujuan.')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('speed')
                            ->label('Kecepatan')
                            ->placeholder('Contoh: 1 Gbps / 10 Gbps')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('vlan')
                            ->label('VLAN')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Aktif',
                                'degraded' => 'Degraded',
                                'disabled' => 'Nonaktif',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Koneksi Utama')
                            ->default(true),
                        Forms\Components\TextInput::make('label')
                            ->label('Label')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sourceDevice.nama')
                    ->label('Sumber / Upstream')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('targetDevice.nama')
                    ->label('Tujuan / Downstream')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sourceInterface.name')
                    ->label('Interface Sumber')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('targetInterface.name')
                    ->label('Interface Tujuan')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('connection_type')
                    ->label('Jenis')
                    ->badge(),
                Tables\Columns\TextColumn::make('source_port')
                    ->label('Port Sumber')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('target_port')
                    ->label('Port Tujuan')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('speed')
                    ->label('Speed')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vlan')
                    ->label('VLAN')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Utama')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'degraded' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber Data')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'topology' ? 'Topologi' : 'Manual')
                    ->color(fn (string $state): string => $state === 'topology' ? 'info' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('connection_type')
                    ->label('Jenis Koneksi')
                    ->options([
                        'ethernet' => 'Ethernet',
                        'fiber' => 'Fiber',
                        'wireless' => 'Wireless',
                        'vpn' => 'VPN',
                        'logical' => 'Logical',
                        'other' => 'Lainnya',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Sumber Data')
                    ->options([
                        'topology' => 'Topologi',
                        'manual' => 'Manual',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (DeviceConnection $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DeviceConnection $record): bool => static::canDelete($record))
                    ->requiresConfirmation(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_topology') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_topology') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof DeviceConnection
            && $record->source !== 'topology'
            && (auth()->user()?->can('update_topology') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof DeviceConnection
            && $record->source !== 'topology'
            && (auth()->user()?->can('delete_topology') ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceConnections::route('/'),
            'create' => Pages\CreateDeviceConnection::route('/create'),
            'edit' => Pages\EditDeviceConnection::route('/{record}/edit'),
        ];
    }
}
