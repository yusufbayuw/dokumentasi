<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
use App\Models\Device;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNetwork extends EditRecord
{
    protected static string $resource = NetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->disabled(function (): bool {
                    $ipIds = $this->record->ip_address()->pluck('id');

                    if ($ipIds->isEmpty()) {
                        return false;
                    }

                    return Device::query()
                        ->whereIn('ip_address_id', $ipIds)
                        ->exists();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
