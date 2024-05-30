<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use Filament\Actions;
use App\Models\Device;
use App\Models\TypeDevice;
use Rats\Zkteco\Lib\ZKTeco;
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
            Actions\Action::make('reboot_fp')
                ->label('Reboot All FP')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->action(function () {
                    $finger_print = TypeDevice::where('nama', 'Finger Print')->first()->id;
                    $devices = Device::where('type_device_id', $finger_print)->get();
                    foreach ($devices as $key => $device) {
                        $ip = $device->ip->nama;
                        $zk = new ZKTeco($ip);
                        $zk->connect();
                        $zk->restart();
                    }
                }),
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
