<?php

namespace App\Filament\Resources\DeviceMovementResource\Pages;

use App\Filament\Resources\DeviceMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceMovements extends ListRecords
{
    protected static string $resource = DeviceMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
