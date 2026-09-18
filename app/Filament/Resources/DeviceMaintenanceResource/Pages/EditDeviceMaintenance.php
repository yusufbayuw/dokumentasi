<?php

namespace App\Filament\Resources\DeviceMaintenanceResource\Pages;

use App\Filament\Resources\DeviceMaintenanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceMaintenance extends EditRecord
{
    protected static string $resource = DeviceMaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }
}
