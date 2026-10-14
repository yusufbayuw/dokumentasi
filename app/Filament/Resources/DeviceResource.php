<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers\InterfacesRelationManager;
use App\Filament\Resources\DeviceResource\RelationManagers\MaintenancesRelationManager;
use App\Filament\Resources\DeviceResource\RelationManagers\MonitoringChecksRelationManager;
use App\Filament\Resources\DeviceResource\RelationManagers\MovementsRelationManager;
use App\Jobs\RestartFingerprintDevice;
use App\Support\FingerprintDevice;
use App\Support\FingerprintManagement;
use App\Support\FingerprintQueue;
use App\Forms\Components\LeafletMap;
use App\Models\Device;
use App\Models\Ruangan;
use App\Services\DeviceHealthService;
use App\Services\IpUsageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $modelLabel = 'Perangkat';
    protected static ?string $navigationLabel = 'Perangkat';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?int $navigationSort = 1;

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
                            ->label('Management IP (Legacy)')
                            ->options(fn (?Device $record): array => app(IpUsageService::class)
                                ->availableForDevice($record?->id))
                            ->searchable()
                            ->helperText('Kompatibilitas sementara. Untuk perangkat multi-interface, tetapkan IP pada Interface Management.'),
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

                Forms\Components\Section::make('Monitoring & Otomasi')
                    ->description('Monitoring berjalan terjadwal. Reboot otomatis bersifat opt-in dan hanya dijalankan untuk perangkat fingerprint.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('monitoring_enabled')
                            ->label('Aktifkan Monitoring')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Toggle::make('auto_reboot_enabled')
                            ->label('Reboot Otomatis Pukul 03:00')
                            ->helperText('Khusus fingerprint. Default nonaktif untuk keamanan operasional.')
                            ->default(false)
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
                Tables\Columns\TextColumn::make('management_ip')
                    ->label('Management IP')
                    ->state(fn (Device $record): ?string => $record->ip?->nama
                        ?? $record->managementInterface?->ipAddress?->nama)
                    ->placeholder('-'),
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
                Tables\Columns\TextColumn::make('health_status')
                    ->label('Health')
                    ->state(fn (Device $record): string => app(DeviceHealthService::class)->status($record))
                    ->formatStateUsing(fn (string $state): string => app(DeviceHealthService::class)->label($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        'stale' => 'warning',
                        'disabled' => 'gray',
                        default => 'info',
                    }),
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
                Tables\Actions\Action::make('manageFingerprint')
                    ->label('Kelola Fingerprint')
                    ->icon('heroicon-o-finger-print')
                    ->color('info')
                    ->visible(fn (Device $record): bool =>
                        FingerprintDevice::isSupported($record) &&
                        FingerprintManagement::canView(auth()->user()))
                    ->modalHeading(fn (Device $record): string => 'Kelola Fingerprint: '.$record->nama)
                    ->modalDescription('Data tersimpan ditampilkan dalam tab. Jalankan operasi melalui formulir di bawah bila memiliki izin.')
                    ->modalWidth('7xl')
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Device $record) => view(
                        'filament.resources.device-resource.modals.manage-fingerprint',
                        FingerprintManagement::data($record, auth()->user()),
                    ))
                    ->form(fn (Device $record): array => FingerprintDevice::canRestart($record, auth()->user()) ? [
                        Forms\Components\Select::make('operation')
                            ->label('Operasi Fingerprint')
                            ->placeholder('Pilih perintah untuk diantrikan')
                            ->options(fn (): array => FingerprintManagement::operationOptions($record, auth()->user()))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('confirmRisk', false)),
                        Forms\Components\Checkbox::make('confirmRisk')
                            ->label('Saya memahami perintah ini mengubah kondisi mesin dan dapat mengganggu absensi.')
                            ->accepted()
                            ->visible(fn (Forms\Get $get): bool => in_array($get('operation'), ['clock', 'reboot'], true)),
                    ] : [])
                    ->modalSubmitActionLabel('Antrikan Operasi')
                    ->modalSubmitAction(fn (\Filament\Actions\StaticAction $action, Device $record) =>
                        $action->visible(FingerprintDevice::canRestart($record, auth()->user())))
                    ->action(function (Device $record, array $data): void {
                        FingerprintManagement::enqueue(
                            $record,
                            auth()->user(),
                            $data['operation'] ?? '',
                            (bool) ($data['confirmRisk'] ?? false),
                        );

                        Notification::make()
                            ->title('Perintah fingerprint diantrikan')
                            ->body('Operasi diproses oleh worker khusus fingerprint. Buka kembali modal untuk melihat hasilnya.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('restart')
                    ->label('Reboot')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reboot perangkat fingerprint?')
                    ->modalDescription('Perangkat akan restart dan dapat tidak tersedia sementara. Pastikan tidak sedang digunakan untuk proses absensi penting.')
                    ->visible(fn (Device $record): bool => FingerprintDevice::canRestart($record, auth()->user()))
                    ->action(function (Device $record): void {
                        abort_unless(FingerprintDevice::canRestart($record, auth()->user()), 403);

                        RestartFingerprintDevice::dispatch(
                            $record->id,
                            auth()->id(),
                            'manual',
                        )->onQueue(FingerprintQueue::NAME);

                        Notification::make()
                            ->title('Perintah reboot diantrikan')
                            ->body("Reboot {$record->nama} akan diproses oleh queue worker.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'ip',
                'managementInterface.ipAddress',
                'type',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InterfacesRelationManager::class,
            MonitoringChecksRelationManager::class,
            MaintenancesRelationManager::class,
            MovementsRelationManager::class,
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
