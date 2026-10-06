<?php

namespace App\Console;

use App\Jobs\PingIpAddress;
use App\Jobs\RestartFingerprintDevice;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $minutes = max(1, min(60, (int) config('cmdb.monitoring.schedule_minutes', 5)));

        $schedule->job(new PingIpAddress())
            ->cron("*/{$minutes} * * * *")
            ->name('monitor-infrastructure')
            ->withoutOverlapping(10);

        $schedule->call(function (): void {
            Device::query()
                ->where('auto_reboot_enabled', true)
                ->whereHas('type', fn ($query) => $query->where('nama', 'like', '%Finger%'))
                ->whereNotNull('ip_address_id')
                ->pluck('id')
                ->each(fn (int $deviceId) => RestartFingerprintDevice::dispatch(
                    $deviceId,
                    null,
                    'scheduled',
                )->onQueue('device-actions'));
        })
            ->name('restart-opted-in-fingerprint-devices')
            ->dailyAt('03:00')
            ->withoutOverlapping(30);
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
