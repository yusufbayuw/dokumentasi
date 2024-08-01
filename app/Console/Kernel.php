<?php

namespace App\Console;

use App\Models\User;
use App\Models\Device;
use App\Models\Pilihan;
use App\Models\TypeDevice;
use App\Jobs\PingIpAddress;
use Rats\Zkteco\Lib\ZKTeco;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            $finger_print = TypeDevice::where('nama', 'Finger Print')->first()->id;
            $devices = Device::where('type_device_id', $finger_print)->get();
            foreach ($devices as $key => $device) {
                $ip = $device->ip->nama;
                $zk = new ZKTeco($ip);
                $zk->connect();
                $zk->restart();
            }
        })->dailyAt('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
