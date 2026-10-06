<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceMonitoringCheck;
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
            $this->recordDeviceChecks($ipAddress, false, null, 'Alamat IPv4 tidak valid.');

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

            $successful = $process->isSuccessful();
            $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
            $latency = $successful ? $this->extractLatency($output) : null;

            $ipAddress->update(['status' => $successful]);

            $this->recordDeviceChecks(
                $ipAddress,
                $successful,
                $latency,
                $successful ? null : $this->summarizeMessage($output),
            );
        } catch (Throwable $exception) {
            $ipAddress->update(['status' => false]);
            $this->recordDeviceChecks($ipAddress, false, null, $exception->getMessage());

            Log::warning('Proses ping gagal dijalankan.', [
                'ip_address_id' => $this->ipId,
                'ip_address' => $this->ipAddress,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function recordDeviceChecks(
        IpAddress $ipAddress,
        bool $successful,
        ?float $latency,
        ?string $message,
    ): void {
        $checkedAt = now();
        $failureThreshold = max(1, (int) config('cmdb.monitoring.offline_after_failures', 3));

        Device::query()
            ->where('monitoring_enabled', true)
            ->where(function ($query) use ($ipAddress): void {
                $query->where('ip_address_id', $ipAddress->id)
                    ->orWhereHas('interfaces', function ($interfaceQuery) use ($ipAddress): void {
                        $interfaceQuery
                            ->where('is_management', true)
                            ->where('ip_address_id', $ipAddress->id);
                    });
            })
            ->get()
            ->each(function (Device $device) use (
                $ipAddress,
                $successful,
                $latency,
                $message,
                $checkedAt,
                $failureThreshold,
            ): void {
                DeviceMonitoringCheck::create([
                    'device_id' => $device->id,
                    'device_name' => $device->nama,
                    'ip_address' => $ipAddress->nama,
                    'check_type' => 'icmp',
                    'status' => $successful ? 'online' : 'offline',
                    'latency_ms' => $latency,
                    'message' => $message,
                    'checked_at' => $checkedAt,
                ]);

                $updates = ['last_checked_at' => $checkedAt];

                if ($successful) {
                    $updates['last_seen_at'] = $checkedAt;
                    $updates['monitoring_status'] = 'online';
                    $updates['consecutive_failures'] = 0;

                    if ($latency !== null) {
                        $updates['last_latency_ms'] = $latency;
                    }
                } else {
                    $failures = ((int) $device->consecutive_failures) + 1;
                    $updates['consecutive_failures'] = $failures;
                    $updates['monitoring_status'] = $failures >= $failureThreshold
                        ? 'offline'
                        : 'unknown';
                }

                $device->forceFill($updates)->saveQuietly();
            });
    }

    private function extractLatency(string $output): ?float
    {
        if (preg_match('/time[=<]([0-9.]+)\s*ms/i', $output, $matches) !== 1) {
            return null;
        }

        return round((float) $matches[1], 2);
    }

    private function summarizeMessage(string $output): ?string
    {
        $message = trim(preg_replace('/\s+/', ' ', $output) ?? '');

        return $message === '' ? null : mb_substr($message, 0, 1000);
    }
}
