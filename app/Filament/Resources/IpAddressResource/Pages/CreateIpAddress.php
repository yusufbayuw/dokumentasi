<?php

namespace App\Filament\Resources\IpAddressResource\Pages;

use App\Filament\Resources\IpAddressResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIpAddress extends CreateRecord
{
    protected static string $resource = IpAddressResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
