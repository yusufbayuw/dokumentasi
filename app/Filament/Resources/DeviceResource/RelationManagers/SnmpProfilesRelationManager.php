<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use App\Filament\Pages\NetworkSnmpData;
use App\Jobs\StartDeviceSnmpSnapshot;
use App\Models\NetworkSnmpProfile;
use App\NetworkMonitoring\SnmpProtocolPolicy;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use App\Rules\SnmpReadOnlyCommunity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SnmpProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'snmpProfiles';
    protected static ?string $title = 'Monitoring SNMP';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.snmp.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Monitoring SNMP')
                ->description('Gunakan SNMPv3 authPriv bila tersedia. SNMPv2c memerlukan community baca-saja dan jaringan manajemen terpercaya.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('version')
                        ->label('Versi SNMP')
                        ->options(fn (?NetworkSnmpProfile $record): array =>
                            (config('network_quality.snmp.v2c_enabled', false) || $record?->version === '2c')
                                ? ['3' => 'SNMPv3 (disarankan)', '2c' => 'SNMPv2c (community)']
                                : ['3' => 'SNMPv3 (disarankan)']
                        )
                        ->default('3')->live()->required()->searchable()->preload()
                        ->helperText('Satu versi per perangkat. SNMPv2c perlu diizinkan dalam konfigurasi server.')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('v2c_warning')
                        ->label('Peringatan SNMPv2c')
                        ->content('Community SNMPv2c dikirim tanpa enkripsi melalui UDP 161. Gunakan community unik dengan hak baca-saja, batasi IP server monitoring dan gunakan VLAN manajemen.')
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '2c')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('community')
                        ->label('Community SNMPv2c')
                        ->password()->revealable()->autocomplete('new-password')
                        ->required(fn (Forms\Get $get, string $operation, ?NetworkSnmpProfile $record): bool =>
                            $get('version') === '2c' && ($operation === 'create' || $record?->version !== '2c'))
                        ->minLength(8)->maxLength(128)
                        ->rules([new SnmpReadOnlyCommunity()])
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn (Forms\Get $get, ?string $state): bool =>
                            $get('version') === '2c' && filled($state))
                        ->helperText('Kosongkan saat edit untuk mempertahankan community lama. Nilainya tidak pernah ditampilkan kembali.')
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '2c')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('security_name')
                        ->label('Nama Pengguna SNMPv3')
                        ->password()->revealable()->autocomplete('new-password')
                        ->required(fn (Forms\Get $get, string $operation, ?NetworkSnmpProfile $record): bool =>
                            $get('version') === '3' && ($operation === 'create' || $record?->version !== '3'))
                        ->maxLength(128)
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn (Forms\Get $get, ?string $state): bool =>
                            $get('version') === '3' && filled($state))
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '3')
                        ->helperText('Kosongkan saat edit untuk mempertahankan pengguna lama.'),
                    Forms\Components\Select::make('auth_protocol')
                        ->label('Autentikasi SNMPv3')
                        ->options(['SHA256' => 'SHA-256', 'SHA512' => 'SHA-512', 'SHA' => 'SHA-1 (kompatibilitas)'])
                        ->default('SHA256')->searchable()->preload()->required()
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '3'),
                    Forms\Components\TextInput::make('auth_passphrase')
                        ->label('Kata Sandi Autentikasi SNMPv3')
                        ->password()->revealable()->autocomplete('new-password')
                        ->minLength(8)
                        ->required(fn (Forms\Get $get, string $operation, ?NetworkSnmpProfile $record): bool =>
                            $get('version') === '3' && ($operation === 'create' || $record?->version !== '3'))
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn (Forms\Get $get, ?string $state): bool =>
                            $get('version') === '3' && filled($state))
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '3')
                        ->helperText('Minimal 8 karakter; kosongkan saat edit jika tidak berubah.'),
                    Forms\Components\TextInput::make('privacy_passphrase')
                        ->label('Kata Sandi Enkripsi AES (SNMPv3)')
                        ->password()->revealable()->autocomplete('new-password')
                        ->minLength(8)
                        ->required(fn (Forms\Get $get, string $operation, ?NetworkSnmpProfile $record): bool =>
                            $get('version') === '3' && ($operation === 'create' || $record?->version !== '3'))
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn (Forms\Get $get, ?string $state): bool =>
                            $get('version') === '3' && filled($state))
                        ->visible(fn (Forms\Get $get): bool => $get('version') === '3')
                        ->helperText('Tidak ditampilkan kembali dan tidak disimpan dalam log.'),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Aktifkan Polling SNMP')
                        ->default(false)
                        ->helperText('Aktifkan setelah koneksi ke perangkat, izin SNMP, dan UDP 161 siap.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label('Versi')->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        $state === '2c' ? 'SNMPv2c' : 'SNMPv3')
                    ->color(fn (string $state): string =>
                        $state === '2c' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('auth_protocol')
                    ->label('Autentikasi')
                    ->getStateUsing(fn (NetworkSnmpProfile $record): string =>
                        $record->version === '3' ? $record->auth_protocol : 'Community'),
                Tables\Columns\TextColumn::make('privacy_label')
                    ->label('Privasi')
                    ->getStateUsing(fn (NetworkSnmpProfile $record): string =>
                        $record->version === '3' ? 'AES / authPriv' : 'Tidak terenkripsi'),
                Tables\Columns\IconColumn::make('enabled')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('last_sample_count')
                    ->label('OID Terakhir')->numeric(),
                Tables\Columns\TextColumn::make('last_poll_error')
                    ->label('Diagnostik')->placeholder('Belum ada')->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_polled_at')
                    ->label('Pengambilan Terakhir')->dateTime('d/m/Y H:i')
                    ->placeholder('Belum ada'),
                Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Atur SNMP')
                    ->visible(fn (): bool => (auth()->user()?->can('update_device') ?? false)
                        && ! $this->getOwnerRecord()->snmpProfiles()->exists()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('update_device') ?? false),
                Tables\Actions\Action::make('capture')
                    ->label('Ambil Data SNMP')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (NetworkSnmpProfile $record): bool =>
                        SnmpProtocolPolicy::canPoll($record)
                        && (bool) $this->getOwnerRecord()->monitoring_enabled
                        && (auth()->user()?->can('update_device') ?? false))
                    ->action(function (NetworkSnmpProfile $record): void {
                        abort_unless(NetworkSnmpData::canAccess(), 403);

                        if (! SnmpProtocolPolicy::canPoll($record)
                            || ! $this->getOwnerRecord()->monitoring_enabled) {
                            return;
                        }

                        $key = 'network-quality:snmp:manual:snapshot:'.$record->device_id;
                        if (! Cache::add($key, now()->timestamp, 60)) {
                            Notification::make()->title('Pengambilan sudah diminta')->warning()->send();
                            return;
                        }

                        StartDeviceSnmpSnapshot::dispatch($record->device_id)
                            ->onQueue('network-quality');
                        Notification::make()->title('Pengambilan OID diantrikan')->success()->send();
                    }),
                Tables\Actions\Action::make('data')
                    ->label('Lihat Data')
                    ->icon('heroicon-o-table-cells')
                    ->url(NetworkSnmpData::getUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => NetworkSnmpData::canAccess()),
            ])
            ->bulkActions([])
            ->emptyStateHeading('SNMP belum dikonfigurasi')
            ->emptyStateDescription('Satu profil per perangkat; semua OID yang diizinkan SNMP akan dikumpulkan menjadi snapshot tanpa input interface manual.');
    }
}
