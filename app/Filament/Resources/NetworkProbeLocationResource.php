<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkProbeLocationResource\Pages;
use App\Models\NetworkProbeLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NetworkProbeLocationResource extends Resource
{
    protected static ?string $model = NetworkProbeLocation::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Lokasi Probe';
    protected static ?string $modelLabel = 'Lokasi Probe';
    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.remote.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny()
            && (auth()->user()?->can('update_device') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Lokasi Monitoring')
                ->description('Daftarkan satu lokasi untuk tiap jaringan/ISP. Agen mengirim hasil pemeriksaan terautentikasi melalui HTTPS.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lokasi')->required()->maxLength(255),
                    Forms\Components\Select::make('unit_id')
                        ->label('Unit')->relationship('unit', 'nama')->searchable()->preload(),
                    Forms\Components\TextInput::make('isp_name')
                        ->label('ISP / Penyedia Jalur')->required()->maxLength(255)
                        ->helperText('Diisi sesuai jalur internet pada lokasi probe (bukan auto-detection).'),
                    Forms\Components\TextInput::make('region')
                        ->label('Wilayah')->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')->columnSpanFull(),
                    Forms\Components\TextInput::make('api_token')
                        ->label('Token Rahasia Agen')
                        ->password()->revealable()->autocomplete('new-password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->minLength(32)->maxLength(256)
                        ->afterStateHydrated(fn (Forms\Components\TextInput $component) => $component->state(null))
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Buat token acak >=32 karakter, simpan di NQ_PROBE_TOKEN pada agen. Tidak dapat dibaca lagi dari aplikasi.')
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Terima Hasil Probe')->default(false)
                        ->helperText('Aktifkan hanya setelah token dan daftar target di agen dipastikan benar.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Lokasi')->searchable(),
                Tables\Columns\TextColumn::make('unit.nama')->label('Unit')->placeholder('-'),
                Tables\Columns\TextColumn::make('isp_name')->label('ISP')->badge()->searchable(),
                Tables\Columns\TextColumn::make('region')->label('Wilayah')->placeholder('-'),
                Tables\Columns\IconColumn::make('enabled')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Terakhir Mengirim')->dateTime('d/m/Y H:i')->placeholder('Belum ada'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNetworkProbeLocations::route('/')];
    }
}
