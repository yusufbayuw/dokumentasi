<?php

namespace App\Observers;

use App\Models\DeviceConnection;
use App\Services\DeviceConnectionIntegrityService;

class DeviceConnectionObserver
{
    public function saving(DeviceConnection $connection): void
    {
        app(DeviceConnectionIntegrityService::class)->validate($connection);
    }
}
