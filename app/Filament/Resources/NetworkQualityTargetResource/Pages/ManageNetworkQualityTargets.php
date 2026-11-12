<?php

namespace App\Filament\Resources\NetworkQualityTargetResource\Pages;

use App\Filament\Resources\NetworkQualityTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNetworkQualityTargets extends ManageRecords
{
    protected static string $resource = NetworkQualityTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
