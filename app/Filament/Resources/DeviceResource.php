<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
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
                Forms\Components\Section::make('Perangkat')
                    ->schema([
                        Forms\Components\Select::make('ip_address_id')
                            ->relationship('ip', 'nama')
                            ->label('IP Address')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('type_device_id')
                            ->relationship('type', 'nama')
                            ->label('Jenis Perangkat')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('ruangan_id')
                            ->relationship('ruangan', 'nama')
                            ->label('Nama Ruangan')
                            ->reactive()
                            ->preload()
                            ->searchable(),
                        Forms\Components\TextInput::make('nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('keterangan')
                            ->maxLength(255),
                        LeafletMap::make('location')
                            ->label('Lokasi Perangkat')
                            ->hidden(fn (Get $get): bool => ! filled($get('ruangan_id')))
                            ->floorImage(function (Get $get): ?string {
                                $roomId = $get('ruangan_id');

                                if (! $roomId) {
                                    return null;
                                }

                                return Ruangan::find($roomId)?->lantai?->image_path;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip.nama')
                    ->label('IP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type.nama')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ruangan.nama')
                    ->label('Lokasi Ruang')
                    ->sortable(),
                Tables\Columns\IconColumn::make('ip.status')
                    ->label('Online')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
