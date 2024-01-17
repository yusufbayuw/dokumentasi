<?php

namespace App\Observers;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Topology;

class DeviceObserver
{
    /**
     * Handle the Device "created" event.
     */
    public function created(Device $device): void
    {
        if ($device->ip_address_id){
            $ipSet = IpAddress::find($device->ip_address_id);
            $ipSet->booked = true;
            $ipSet->save();
        }
        
        Topology::create([
            "device_id" => $device->id,
        ]);
    }

    /**
     * Handle the Device "updated" event.
     */
    public function updated(Device $device): void
    {
        $ipSet = IpAddress::find($device->getOriginal('ip_address_id'));
        $ipSet->booked = false;
        $ipSet->save();

        $ipSet = IpAddress::find($device->ip_address_id);
        $ipSet->booked = false;
        $ipSet->save();
    }

    /**
     * Handle the Device "deleted" event.
     */
    public function deleted(Device $device): void
    {
        $ipSet = IpAddress::find($device->getOriginal('ip_address_id'));
        $ipSet->booked = false;
        $ipSet->save();
    }

    /**
     * Handle the Device "restored" event.
     */
    public function restored(Device $device): void
    {
        //
    }

    /**
     * Handle the Device "force deleted" event.
     */
    public function forceDeleted(Device $device): void
    {
        //
    }
}
