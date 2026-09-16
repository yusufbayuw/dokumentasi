<?php

namespace App\Jobs;

use App\Models\IpAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class SinglePingIpAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 10;

    public function __construct(
        public string $ipAddress,
        public int $ipId,
    ) {
    }

    public function handle(): void
    {
        $ipAddress = IpAddress::find($this->ipId);

        if (! $ipAddress) {
            return;
        }

        if (filter_var($this->ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $ipAddress->update(['status' => false]);

            Log::warning('Ping dibatalkan karena alamat IPv4 tidak valid.', [
                'ip_address_id' => $this->ipId,
                'ip_address' => $this->ipAddress,
            ]);

            return;
        }

        try {
            $process = new Process([
                'ping',
                '-c',
                '1',
                '-W',
                '2',
                $this->ipAddress,
            ]);

            $process->setTimeout(5);
            $process->run();

            $ipAddress->update([
                'status' => $process->isSuccessful(),
            ]);
        } catch (Throwable $exception) {
            $ipAddress->update(['status' => false]);

            Log::warning('Proses ping gagal dijalankan.', [
                'ip_address_id' => $this->ipId,
                'ip_address' => $this->ipAddress,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
