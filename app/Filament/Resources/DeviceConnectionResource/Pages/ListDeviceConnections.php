<?php

namespace App\Filament\Resources\DeviceConnectionResource\Pages;

use App\Filament\Resources\DeviceConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceConnections extends ListRecords
{
    protected static string $resource = DeviceConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
