<?php

namespace App\Filament\Resources\Shield\RoleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\Shield\RoleResource;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getActions(): array
    {
        return [
            ExportAction::make()->exports([
                ExcelExport::make('tabel')->fromTable(),
                ExcelExport::make('form')->fromForm(),
            ]),
            Actions\CreateAction::make(),
        ];
    }
}
