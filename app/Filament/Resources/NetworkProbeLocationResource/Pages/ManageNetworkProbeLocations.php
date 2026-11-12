<?php

namespace App\Filament\Resources\NetworkProbeLocationResource\Pages;

use App\Filament\Resources\NetworkProbeLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNetworkProbeLocations extends ManageRecords
{
    protected static string $resource = NetworkProbeLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
