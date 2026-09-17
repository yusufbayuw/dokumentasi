<?php

namespace App\Filament\Resources\DeviceConnectionResource\Pages;

use App\Filament\Resources\DeviceConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceConnection extends CreateRecord
{
    protected static string $resource = DeviceConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = 'manual';

        return $data;
    }
}
