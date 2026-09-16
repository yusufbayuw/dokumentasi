<?php

namespace App\Filament\Resources\TypeDeviceResource\Pages;

use App\Filament\Resources\TypeDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTypeDevice extends EditRecord
{
    protected static string $resource = TypeDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->record->device()->exists()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
