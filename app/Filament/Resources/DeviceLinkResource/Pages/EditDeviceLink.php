<?php

namespace App\Filament\Resources\DeviceLinkResource\Pages;

use App\Filament\Resources\DeviceLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeviceLink extends EditRecord
{
    protected static string $resource = DeviceLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
