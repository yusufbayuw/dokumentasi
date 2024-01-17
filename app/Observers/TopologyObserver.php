<?php

namespace App\Observers;

use App\Models\Device;
use App\Models\Topology;

class TopologyObserver
{
    /**
     * Handle the Topology "created" event.
     */
    public function created(Topology $topology): void
    {
        //$device = Device::find($topology->device_id);
        //$topology->title = $device->nama;
        //$topology->save();
    }

    /**
     * Handle the Topology "updated" event.
     */
    public function updated(Topology $topology): void
    {
        //
    }

    /**
     * Handle the Topology "deleted" event.
     */
    public function deleted(Topology $topology): void
    {
        //
    }

    /**
     * Handle the Topology "restored" event.
     */
    public function restored(Topology $topology): void
    {
        //
    }

    /**
     * Handle the Topology "force deleted" event.
     */
    public function forceDeleted(Topology $topology): void
    {
        //
    }
}
