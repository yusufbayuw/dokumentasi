<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceMaintenanceResource\Pages;
use App\Models\DeviceMaintenance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeviceMaintenanceResource extends Resource
{
    protected static ?string $model = DeviceMaintenance::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Maintenance';
    protected static ?string $modelLabel = 'Maintenance';
    protected static ?string $pluralModelLabel = 'Maintenance';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('device_id')
                    ->relationship('device', 'nama')
                    ->label('Perangkat')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('maintenance_type')
                    ->label('Jenis Maintenance')
                    ->options([
                        'inspection' => 'Inspeksi',
                        'preventive' => 'Preventive',
                        'corrective' => 'Corrective',
                        'upgrade' => 'Upgrade',
                        'replacement' => 'Penggantian Komponen',
                        'other' => 'Lainnya',
                    ])
                    ->default('inspection')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->live()
                    ->options([
                        'planned' => 'Direncanakan',
                        'in_progress' => 'Berjalan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->default('planned')
                    ->required(),
                Forms\Components\DateTimePicker::make('performed_at')
                    ->label('Waktu Maintenance')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                Forms\Components\DateTimePicker::make('next_due_at')
                    ->label('Jadwal Berikutnya')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->rule('after_or_equal:performed_at'),
                Forms\Components\TextInput::make('performed_by')
                    ->label('Teknisi / Pelaksana')
                    ->maxLength(255),
                Forms\Components\TextInput::make('vendor')
                    ->label('Vendor')
                    ->maxLength(255),
                Forms\Components\TextInput::make('cost')
                    ->label('Biaya')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0),
                Forms\Components\Textarea::make('description')
                    ->label('Pekerjaan / Keluhan')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('result')
                    ->label('Hasil / Tindakan')
                    ->required(fn (Get $get): bool => $get('status') === 'completed')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DeviceMaintenance $record): ?string => $record->device?->asset_code),
                Tables\Columns\TextColumn::make('maintenance_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inspection' => 'Inspeksi',
                        'preventive' => 'Preventive',
                        'corrective' => 'Corrective',
                        'upgrade' => 'Upgrade',
                        'replacement' => 'Penggantian',
                        default => 'Lainnya',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'planned' => 'Direncanakan',
                        'in_progress' => 'Berjalan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'planned' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Jadwal Berikutnya')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->color(fn (DeviceMaintenance $record): string => $record->next_due_at?->isPast() && $record->status !== 'completed' ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('performed_by')
                    ->label('Pelaksana')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vendor')
                    ->label('Vendor')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Biaya')
                    ->money('IDR')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('maintenance_type')
                    ->label('Jenis')
                    ->options([
                        'inspection' => 'Inspeksi',
                        'preventive' => 'Preventive',
                        'corrective' => 'Corrective',
                        'upgrade' => 'Upgrade',
                        'replacement' => 'Penggantian',
                        'other' => 'Lainnya',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'planned' => 'Direncanakan',
                        'in_progress' => 'Berjalan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
                Tables\Filters\Filter::make('overdue')
                    ->label('Jadwal Terlewat')
                    ->query(fn ($query) => $query
                        ->whereNotNull('next_due_at')
                        ->where('next_due_at', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('performed_at', 'desc');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceMaintenances::route('/'),
            'create' => Pages\CreateDeviceMaintenance::route('/create'),
            'edit' => Pages\EditDeviceMaintenance::route('/{record}/edit'),
        ];
    }
}
