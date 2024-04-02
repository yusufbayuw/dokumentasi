<?php

namespace App\Filament\Resources\IpAddressResource\Pages;

use Filament\Actions;
use App\Models\IpAddress;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\IpAddressResource;
use App\Jobs\PingIpAddress;

class ListIpAddresses extends ListRecords
{
    protected static string $resource = IpAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('test')->action(function () {
                PingIpAddress::dispatch();
            })
        ];
    }
}
