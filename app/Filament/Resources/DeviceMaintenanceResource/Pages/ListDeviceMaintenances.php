<?php

namespace App\Filament\Resources\DeviceMaintenanceResource\Pages;

use App\Filament\Resources\DeviceMaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceMaintenances extends ListRecords
{
    protected static string $resource = DeviceMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
