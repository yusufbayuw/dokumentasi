<?php

namespace App\Filament\Resources\TopologyResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TopologyResource;
use EightyNine\ExcelImport\ExcelImportAction;
use App\Filament\Resources\TopologyResource\Widgets\TopologyWidget;
use App\Imports\TopologyImport;

class ListTopologies extends ListRecords
{
    protected static string $resource = TopologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make('update')
                ->label('Update')
                ->icon('heroicon-o-arrow-path')
                ->color("success")
                ->use(TopologyImport::class),
            ExcelImportAction::make('import')
                ->color("info")
                ->icon('heroicon-o-arrow-up-tray'),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TopologyWidget::class
        ];
    }
}
