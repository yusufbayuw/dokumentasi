<?php

namespace App\Jobs;

use App\Models\Device;
use App\Support\FingerprintQueue;
use App\Services\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\ZKTeco;
use Throwable;

class RestartFingerprintDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 20;

    public function __construct(
        public int $deviceId,
        public ?int $requestedBy = null,
        public string $source = 'scheduled',
    ) {
        $this->onConnection(FingerprintQueue::CONNECTION)
            ->onQueue(FingerprintQueue::NAME);
    }

    public function handle(): void
    {
        $device = Device::query()
            ->with(['ip', 'type', 'managementInterface.ipAddress'])
            ->find($this->deviceId);

        if (! $device) {
            return;
        }

        $typeName = strtolower((string) $device->type?->nama);

        if (! str_contains($typeName, 'finger')) {
            Log::warning('Reboot fingerprint dibatalkan karena tipe perangkat tidak sesuai.', [
                'device_id' => $device->id,
                'source' => $this->source,
            ]);

            return;
        }

        $ip = $device->ip?->nama
            ?? $device->managementInterface?->ipAddress?->nama;

        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            Log::warning('Reboot fingerprint dibatalkan karena management IPv4 tidak valid.', [
                'device_id' => $device->id,
                'ip_address' => $ip,
                'source' => $this->source,
            ]);

            return;
        }

        try {
            $zk = new ZKTeco($ip);

            if ($zk->connect() === false) {
                throw new \RuntimeException('Tidak dapat terhubung ke perangkat fingerprint.');
            }

            $zk->restart();

            if ($this->source === 'scheduled') {
                $device->forceFill(['last_auto_reboot_at' => now()])->saveQuietly();
            }

            app(ActivityLogger::class)->log(
                'device.reboot',
                $device,
                [],
                ['ip_address' => $ip, 'source' => $this->source],
                'Perintah reboot fingerprint dikirim.',
                $this->requestedBy,
            );

            Log::notice('Perintah reboot fingerprint berhasil dikirim.', [
                'device_id' => $device->id,
                'ip_address' => $ip,
                'source' => $this->source,
                'requested_by' => $this->requestedBy,
            ]);
        } catch (Throwable $exception) {
            Log::error('Reboot perangkat fingerprint gagal.', [
                'device_id' => $device->id,
                'ip_address' => $ip,
                'source' => $this->source,
                'requested_by' => $this->requestedBy,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $device = Device::query()->find($this->deviceId);

        if (! $device) {
            return;
        }

        app(ActivityLogger::class)->log(
            'device.reboot_failed',
            $device,
            [],
            ['source' => $this->source],
            'Reboot fingerprint gagal setelah seluruh retry: ' . $exception->getMessage(),
            $this->requestedBy,
        );
    }

    public function backoff(): array
    {
        return [30, 120];
    }
}
