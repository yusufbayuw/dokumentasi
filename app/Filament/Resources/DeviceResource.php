<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers\MonitoringChecksRelationManager;
use App\Forms\Components\LeafletMap;
use App\Models\Device;
use App\Models\Ruangan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Rats\Zkteco\Lib\ZKTeco;
use Throwable;

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
                Forms\Components\Section::make('Identitas CMDB')
                    ->description('Identitas perangkat yang stabil dan dapat digunakan untuk inventaris, audit, serta pencarian.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Perangkat')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type_device_id')
                            ->relationship('type', 'nama')
                            ->label('Jenis Perangkat')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('asset_code')
                            ->label('Kode Aset')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('hostname')
                            ->label('Hostname')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mac_address')
                            ->label('MAC Address')
                            ->placeholder('AA:BB:CC:DD:EE:FF')
                            ->maxLength(32)
                            ->regex('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/'),
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('manufacturer')
                            ->label('Manufacturer')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('model')
                            ->label('Model')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('firmware_version')
                            ->label('Firmware / Versi')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Jaringan & Lokasi')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('ip_address_id')
                            ->relationship('ip', 'nama')
                            ->label('IP Address')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('ruangan_id')
                            ->relationship('ruangan', 'nama')
                            ->label('Nama Ruangan')
                            ->reactive()
                            ->preload()
                            ->searchable(),
                        LeafletMap::make('location')
                            ->label('Lokasi Perangkat')
                            ->hidden(fn (Get $get): bool => ! filled($get('ruangan_id')))
                            ->floorImage(function (Get $get): ?string {
                                $roomId = $get('ruangan_id');

                                if (! $roomId) {
                                    return null;
                                }

                                return Ruangan::find($roomId)?->lantai?->image_path;
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Kepemilikan & Lifecycle')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('owner_unit_id')
                            ->relationship('ownerUnit', 'nama')
                            ->label('Unit Pemilik')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('pic')
                            ->label('PIC / Penanggung Jawab')
                            ->maxLength(255),
                        Forms\Components\Select::make('lifecycle_status')
                            ->label('Status Lifecycle')
                            ->options(self::lifecycleOptions())
                            ->default('active')
                            ->required(),
                        Forms\Components\Select::make('criticality')
                            ->label('Criticality')
                            ->options(self::criticalityOptions())
                            ->default('normal')
                            ->required(),
                    ]),

                Forms\Components\Section::make('Pengadaan & Garansi')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\TextInput::make('vendor')
                            ->label('Vendor')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('warranty_until')
                            ->label('Garansi Sampai')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),

                Forms\Components\Section::make('Monitoring')
                    ->description('Jika aktif, perangkat akan ikut proses monitoring ICMP dan menyimpan riwayat status serta latency.')
                    ->schema([
                        Forms\Components\Toggle::make('monitoring_enabled')
                            ->label('Aktifkan Monitoring')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('asset_code')
                    ->label('Kode Aset')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Perangkat')
                    ->searchable()
                    ->description(fn (Device $record): ?string => $record->hostname),
                Tables\Columns\TextColumn::make('ip.nama')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type.nama')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ruangan.nama')
                    ->label('Lokasi Ruang')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label('Lifecycle')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::lifecycleOptions()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'spare' => 'info',
                        'repair' => 'warning',
                        'retired', 'disposed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('criticality')
                    ->label('Kritis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::criticalityOptions()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('ip.status')
                    ->label('Online')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_latency_ms')
                    ->label('Latency')
                    ->suffix(' ms')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('warranty_until')
                    ->label('Garansi')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('lifecycle_status')
                    ->label('Lifecycle')
                    ->options(self::lifecycleOptions()),
                Tables\Filters\SelectFilter::make('criticality')
                    ->label('Criticality')
                    ->options(self::criticalityOptions()),
                Tables\Filters\TernaryFilter::make('monitoring_enabled')
                    ->label('Monitoring'),
                Tables\Filters\SelectFilter::make('type_device_id')
                    ->label('Jenis Perangkat')
                    ->relationship('type', 'nama')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('restart')
                    ->label('Reboot')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reboot perangkat fingerprint?')
                    ->modalDescription('Perangkat akan restart dan dapat tidak tersedia sementara. Pastikan tidak sedang digunakan untuk proses absensi penting.')
                    ->visible(function (Device $device): bool {
                        return ($device->type?->nama === 'Finger Print')
                            && (auth()->user()?->can('update_device') ?? false);
                    })
                    ->action(function (Device $device): void {
                        $ip = $device->ip?->nama;

                        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                            Notification::make()
                                ->title('Reboot dibatalkan')
                                ->body('Perangkat tidak memiliki alamat IPv4 yang valid.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $zk = new ZKTeco($ip);
                            $connected = $zk->connect();

                            if ($connected === false) {
                                Log::warning('Gagal terhubung ke perangkat fingerprint untuk reboot.', [
                                    'device_id' => $device->id,
                                    'ip_address' => $ip,
                                    'user_id' => auth()->id(),
                                ]);

                                Notification::make()
                                    ->title('Reboot gagal')
                                    ->body('Tidak dapat terhubung ke perangkat fingerprint.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $zk->restart();

                            Log::notice('Perangkat fingerprint direboot dari aplikasi.', [
                                'device_id' => $device->id,
                                'ip_address' => $ip,
                                'user_id' => auth()->id(),
                            ]);

                            Notification::make()
                                ->title('Perintah reboot dikirim')
                                ->body("Perintah reboot telah dikirim ke {$device->nama} ({$ip}).")
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Log::error('Reboot perangkat fingerprint gagal.', [
                                'device_id' => $device->id,
                                'ip_address' => $ip,
                                'user_id' => auth()->id(),
                                'error' => $exception->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Reboot gagal')
                                ->body('Terjadi kesalahan saat menghubungi perangkat. Detail dicatat pada log aplikasi.')
                                ->danger()
                                ->send();
                        }
                    }),
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
        return [
            MonitoringChecksRelationManager::class,
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

    private static function lifecycleOptions(): array
    {
        return [
            'active' => 'Aktif',
            'spare' => 'Cadangan',
            'repair' => 'Perbaikan',
            'retired' => 'Pensiun',
            'disposed' => 'Dihapuskan',
        ];
    }

    private static function criticalityOptions(): array
    {
        return [
            'critical' => 'Critical',
            'high' => 'High',
            'normal' => 'Normal',
            'low' => 'Low',
        ];
    }
}
