<?php

namespace App\Filament\Resources\TopologyResource\Pages;

use App\Filament\Resources\TopologyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTopology extends CreateRecord
{
    protected static string $resource = TopologyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
