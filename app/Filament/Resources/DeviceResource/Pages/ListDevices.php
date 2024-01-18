<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use Filament\Actions;
use App\Models\TypeDevice;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\DeviceResource;
use EightyNine\ExcelImport\ExcelImportAction;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make('import')
                ->color("info")
                ->icon('heroicon-o-arrow-up-tray'),
            Actions\CreateAction::make(),
        ];
    }
    
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')->badge($this->getModel()::count())];
 
        $types = TypeDevice::withCount('device')
            ->get();
 
        foreach ($types as $type) {
            $name = $type->kode;
            $slug = str($name)->slug()->toString();
 
            $tabs[$slug] = Tab::make($name)
                ->badge($type->device_count)
                ->modifyQueryUsing(function ($query) use ($type) {
                    return $query->where('type_device_id', $type->id);
                });
        }
 
        return $tabs;
    }

}
