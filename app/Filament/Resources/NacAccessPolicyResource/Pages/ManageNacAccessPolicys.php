<?php

namespace App\Filament\Resources\NacAccessPolicyResource\Pages;

use App\Filament\Resources\NacAccessPolicyResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Actions;

class ManageNacAccessPolicys extends ManageRecords
{
    protected static string $resource = NacAccessPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
