<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SnmpProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'snmpProfiles';
    protected static ?string $title = 'Monitoring SNMPv3';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.snmp.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kredensial Monitoring (SNMPv3)')
                ->description('Khusus authPriv (autentikasi + enkripsi AES). Gunakan akun SNMP baca-saja pada perangkat. Kata sandi tidak pernah ditampilkan kembali.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('security_name')
                        ->label('Nama Pengguna SNMPv3')
                        ->password()->revealable()->autocomplete('new-password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(128)
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Kosongkan saat edit untuk mempertahankan pengguna lama.'),
                    Forms\Components\Select::make('auth_protocol')
                        ->label('Autentikasi')
                        ->options(['SHA256' => 'SHA-256', 'SHA512' => 'SHA-512', 'SHA' => 'SHA-1 (kompatibilitas)'])
                        ->default('SHA256')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('auth_passphrase')
                        ->label('Kata Sandi Autentikasi')
                        ->password()->revealable()->autocomplete('new-password')
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Minimal 8 karakter; kosongkan saat edit jika tidak berubah.'),
                    Forms\Components\TextInput::make('privacy_passphrase')
                        ->label('Kata Sandi Enkripsi AES')
                        ->password()->revealable()->autocomplete('new-password')
                        ->minLength(8)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Minimal 8 karakter; tidak disimpan dalam log atau hasil monitoring.'),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Aktifkan Polling SNMP')
                        ->default(false)
                        ->helperText('Aktifkan setelah ekstensi PHP SNMP dan akses UDP 161 dipastikan tersedia.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('auth_protocol')->label('Autentikasi')->badge(),
                Tables\Columns\TextColumn::make('privacy_label')
                    ->label('Privasi')->getStateUsing(fn (): string => 'AES / authPriv'),
                Tables\Columns\IconColumn::make('enabled')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Diperbarui')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Atur SNMPv3')
                    ->visible(fn (): bool => (auth()->user()?->can('update_device') ?? false)
                        && ! $this->getOwnerRecord()->snmpProfiles()->exists()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('update_device') ?? false),
            ])
            ->bulkActions([])
            ->emptyStateHeading('SNMPv3 belum dikonfigurasi')
            ->emptyStateDescription('Monitoring interface bersifat opsional; konfigurasi satu profil per perangkat.');
    }
}
