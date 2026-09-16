<?php

namespace App\Console;

use App\Models\Device;
use App\Models\TypeDevice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\ZKTeco;
use Throwable;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            $fingerPrintTypeId = TypeDevice::query()
                ->where('nama', 'Finger Print')
                ->value('id');

            if (! $fingerPrintTypeId) {
                Log::warning('Scheduled fingerprint reboot dilewati karena tipe perangkat Finger Print tidak ditemukan.');

                return;
            }

            Device::query()
                ->with('ip')
                ->where('type_device_id', $fingerPrintTypeId)
                ->whereNotNull('ip_address_id')
                ->get()
                ->each(function (Device $device): void {
                    $ip = $device->ip?->nama;

                    if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                        Log::warning('Scheduled fingerprint reboot dilewati karena IPv4 tidak valid.', [
                            'device_id' => $device->id,
                            'ip_address' => $ip,
                        ]);

                        return;
                    }

                    try {
                        $zk = new ZKTeco($ip);
                        $connected = $zk->connect();

                        if ($connected === false) {
                            Log::warning('Scheduled fingerprint reboot gagal terhubung.', [
                                'device_id' => $device->id,
                                'ip_address' => $ip,
                            ]);

                            return;
                        }

                        $zk->restart();

                        Log::info('Scheduled fingerprint reboot berhasil dikirim.', [
                            'device_id' => $device->id,
                            'ip_address' => $ip,
                        ]);
                    } catch (Throwable $exception) {
                        Log::error('Scheduled fingerprint reboot gagal.', [
                            'device_id' => $device->id,
                            'ip_address' => $ip,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                });
        })
            ->name('restart-fingerprint-devices')
            ->dailyAt('03:00')
            ->withoutOverlapping(30);
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
