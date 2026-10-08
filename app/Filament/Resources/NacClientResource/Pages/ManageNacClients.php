<?php

namespace App\Filament\Resources\NacClientResource\Pages;

use App\Filament\Resources\NacClientResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;

class ManageNacClients extends ManageRecords
{
    protected static string $resource = NacClientResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
