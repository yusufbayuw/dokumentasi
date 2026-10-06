<?php

namespace App\Filament\Resources\IpAddressResource\Pages;

use App\Filament\Resources\IpAddressResource;
use App\Jobs\PingIpAddress;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListIpAddresses extends ListRecords
{
    protected static string $resource = IpAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test')
                ->label('Ping Semua IP')
                ->icon('heroicon-o-signal')
                ->requiresConfirmation()
                ->modalHeading('Jalankan ping ke semua perangkat?')
                ->modalDescription('Sistem akan menjalankan ping ke management IP yang sedang direferensikan perangkat.')
                ->visible(fn (): bool => auth()->user()?->can('update_ip_address') ?? false)
                ->action(function (): void {
                    PingIpAddress::dispatch()->onQueue('monitoring');

                    Notification::make()
                        ->title('Proses ping diantrikan')
                        ->body('Status perangkat akan diperbarui oleh queue worker monitoring.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
