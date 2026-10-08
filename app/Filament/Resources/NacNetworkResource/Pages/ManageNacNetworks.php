<?php

namespace App\Filament\Resources\NacNetworkResource\Pages;

use App\Filament\Resources\NacNetworkResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;

class ManageNacNetworks extends ManageRecords
{
    protected static string $resource = NacNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
