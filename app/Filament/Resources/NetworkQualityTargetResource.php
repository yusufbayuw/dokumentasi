<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkQualityTargetResource\Pages;
use App\Models\NetworkQualityTarget;
use App\NetworkMonitoring\TargetGuard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NetworkQualityTargetResource extends Resource
{
    protected static ?string $model = NetworkQualityTarget::class;
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Target Layanan';
    protected static ?string $modelLabel = 'Target Monitoring';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return config('network_quality.enabled', false)
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
            Forms\Components\Section::make('Target Layanan')
                ->description('Perangkat CMDB aktif dipantau otomatis. Tambahkan target ini hanya untuk DNS, TCP, HTTPS atau pemeriksaan khusus.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Target')->required()->maxLength(255),
                    Forms\Components\Select::make('probe_type')
                        ->label('Metode')
                        ->options(['icmp' => 'Ping ICMP', 'dns' => 'DNS', 'tcp' => 'TCP', 'https' => 'HTTPS'])
                        ->required()
                        ->default('dns')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('port', null);
                            $set('expected_status', null);
                        }),
                    Forms\Components\TextInput::make('address')
                        ->label('Alamat IP / Domain / URL HTTPS')
                        ->required()
                        ->maxLength(512)
                        ->helperText('Gunakan IP untuk ICMP/TCP, nama domain untuk DNS, dan URL https:// untuk HTTPS. Domain/rentang harus terdaftar di allowlist server.')
                        ->columnSpanFull()
                        ->rules(fn (Get $get): array => [
                            function (string $attribute, $value, \Closure $fail) use ($get): void {
                                $port = filled($get('port')) ? (int) $get('port') : null;
                                if (! app(TargetGuard::class)->allows(
                                    (string) $get('probe_type'), (string) $value, $port
                                )) {
                                    $fail('Alamat atau port tidak diizinkan oleh kebijakan monitoring server.');
                                }
                            },
                        ]),
                    Forms\Components\TextInput::make('port')
                        ->label('Port TCP')
                        ->numeric()->minValue(1)->maxValue(65535)
                        ->required(fn (Get $get): bool => $get('probe_type') === 'tcp')
                        ->visible(fn (Get $get): bool => $get('probe_type') === 'tcp'),
                    Forms\Components\TextInput::make('expected_status')
                        ->label('HTTP Status Diharapkan (opsional)')
                        ->numeric()->minValue(100)->maxValue(599)
                        ->visible(fn (Get $get): bool => $get('probe_type') === 'https'),
                    Forms\Components\TextInput::make('interval_minutes')
                        ->label('Interval (menit)')
                        ->numeric()->minValue(1)->maxValue(60)->required()->default(5),
                    Forms\Components\TextInput::make('samples')
                        ->label('Jumlah Sampel Ping')
                        ->numeric()->minValue(2)->maxValue(10)->required()->default(3)
                        ->visible(fn (Get $get): bool => $get('probe_type') === 'icmp'),
                    Forms\Components\TextInput::make('timeout_seconds')
                        ->label('Timeout (detik)')
                        ->numeric()->minValue(1)->maxValue(5)->required()->default(2),
                    Forms\Components\Toggle::make('enabled')
                        ->label('Monitoring Aktif')
                        ->default(false)
                        ->helperText('Nonaktif secara default. Aktifkan setelah target dapat dijangkau oleh server/worker.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('probe_type')->label('Metode')->badge(),
                Tables\Columns\TextColumn::make('address')->label('Tujuan')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('interval_minutes')->label('Interval')->suffix(' menit'),
                Tables\Columns\IconColumn::make('enabled')->label('Aktif')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNetworkQualityTargets::route('/')];
    }
}
