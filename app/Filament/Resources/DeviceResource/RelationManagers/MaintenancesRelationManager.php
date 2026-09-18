<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenancesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenances';

    protected static ?string $title = 'Riwayat Maintenance';

    public function form(Form $form): Form
    {
        return $form->schema([
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
                ->options([
                    'planned' => 'Direncanakan',
                    'in_progress' => 'Berjalan',
                    'completed' => 'Selesai',
                    'cancelled' => 'Dibatalkan',
                ])
                ->default('completed')
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
                ->seconds(false),
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
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('performed_by')
                    ->label('Pelaksana')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Berikutnya')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cost')
                    ->label('Biaya')
                    ->money('IDR')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('result')
                    ->label('Hasil')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->defaultSort('performed_at', 'desc');
    }
}
