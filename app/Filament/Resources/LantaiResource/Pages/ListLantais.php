<?php

namespace App\Filament\Resources\LantaiResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\LantaiResource;
use EightyNine\ExcelImport\ExcelImportAction;

class ListLantais extends ListRecords
{
    protected static string $resource = LantaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make('import')
                ->color("info")
                ->icon('heroicon-o-arrow-up-tray'),
            Actions\CreateAction::make(),
        ];
    }
}
