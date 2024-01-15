<?php

namespace App\Filament\Resources\RuanganResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\RuanganResource;
use EightyNine\ExcelImport\ExcelImportAction;

class ListRuangans extends ListRecords
{
    protected static string $resource = RuanganResource::class;

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
