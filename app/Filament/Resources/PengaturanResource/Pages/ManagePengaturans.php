<?php

namespace App\Filament\Resources\PengaturanResource\Pages;

use App\Filament\Resources\PengaturanResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePengaturans extends ManageRecords
{
    protected static string $resource = PengaturanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
