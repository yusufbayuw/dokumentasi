<?php

namespace App\Filament\Resources\NacCertificateResource\Pages;

use App\Filament\Resources\NacCertificateResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;

class ManageNacCertificates extends ManageRecords
{
    protected static string $resource = NacCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
