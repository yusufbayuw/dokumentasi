<?php

namespace App\Filament\Resources\TopologyResource\Pages;

use App\Filament\Resources\TopologyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTopology extends EditRecord
{
    protected static string $resource = TopologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
