<?php

namespace App\Filament\Resources\DeviceMaintenanceResource\Pages;

use App\Filament\Resources\DeviceMaintenanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceMaintenance extends CreateRecord
{
    protected static string $resource = DeviceMaintenanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
