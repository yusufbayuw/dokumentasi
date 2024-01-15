<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions;
use App\Filament\Resources\UserResource;
use App\Imports\MyUserImport;
use Filament\Resources\Pages\ListRecords;
use EightyNine\ExcelImport\ExcelImportAction;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $userAuth = auth()->user();
        return [
            ExcelImportAction::make('update')
                ->label('Update')
                ->icon('heroicon-o-arrow-path')
                ->color("success")
                ->use(MyUserImport::class)
                ->hidden(!$userAuth->hasRole(['super_admin', 'guru_bk'])),
            ExcelImportAction::make('import')
                ->color("info")
                ->icon('heroicon-o-arrow-up-tray')
                ->hidden(!$userAuth->hasRole(['super_admin', 'guru_bk'])),
            Actions\CreateAction::make(),
        ];
    }
}
