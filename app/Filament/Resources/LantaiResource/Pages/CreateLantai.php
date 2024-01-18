<?php

namespace App\Filament\Resources\LantaiResource\Pages;

use App\Filament\Resources\LantaiResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLantai extends CreateRecord
{
    protected static string $resource = LantaiResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
