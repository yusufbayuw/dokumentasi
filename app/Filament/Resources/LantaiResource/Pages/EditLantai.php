<?php

namespace App\Filament\Resources\LantaiResource\Pages;

use App\Filament\Resources\LantaiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLantai extends EditRecord
{
    protected static string $resource = LantaiResource::class;

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
