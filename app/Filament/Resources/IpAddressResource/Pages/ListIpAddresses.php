<?php

namespace App\Filament\Resources\IpAddressResource\Pages;

use Filament\Actions;
use App\Models\IpAddress;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\IpAddressResource;

class ListIpAddresses extends ListRecords
{
    protected static string $resource = IpAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('test')->action(function () {
                $ipAddresses = IpAddress::where('booked', true)->get(); // Assuming IPAddress is your Eloquent model

                foreach ($ipAddresses as $ipAddress) {

                    $output = exec("ping -c 1 " . $ipAddress->nama, $results, $return);
                    dd($results);
                    if ($return === 0) {
                        $ipAddress->status = true;
                        $ipAddress->save();
                    }
                }
            })
        ];
    }
}
