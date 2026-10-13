<?php

namespace App\Services;

use App\Jobs\RestartFingerprintDevice;
use App\Models\Device;
use App\Models\User;
use App\Support\FingerprintDevice;
use App\Support\FingerprintQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FingerprintBulkRebootService
{
    // Release reboot jobs two seconds apart into the isolated fingerprint queue.
    // Actual command timing depends on the worker and machine response.
    public const SPACING_SECONDS = 2;

    public static function canRequest(?User $user): bool
    {
        return $user !== null && $user->hasRole('super_admin');
    }

    public static function isEligible(Device $device): bool
    {
        if (! FingerprintDevice::isSupported($device)) {
            return false;
        }

        try {
            FingerprintDevice::address($device);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function delaySeconds(int $position): int
    {
        return max(0, $position) * self::SPACING_SECONDS;
    }

    /**
     * A snapshot for the confirmation modal. This deliberately ignores
     * current table tabs/filters: Reboot All means all fingerprint devices.
     *
     * @return array{total:int,eligible:int,skipped:int,ids:array<int,int>}
     */
    public function plan(): array
    {
        $ids = [];
        $total = 0;
        $skipped = 0;

        Device::query()
            ->with(['type', 'ip', 'managementInterface.ipAddress'])
            ->chunkById(150, function ($devices) use (&$ids, &$total, &$skipped): void {
                foreach ($devices as $device) {
                    if (! FingerprintDevice::isSupported($device)) {
                        continue;
                    }

                    $total++;

                    if (! self::isEligible($device)) {
                        $skipped++;

                        continue;
                    }

                    $ids[] = (int) $device->getKey();
                }
            });

        return [
            'total' => $total,
            'eligible' => count($ids),
            'skipped' => $skipped,
            'ids' => $ids,
        ];
    }

    /**
     * Schedule manual reboot jobs. No machine commands run in this request.
     * A cooldown prevents accidentally scheduling overlapping full-fleet runs.
     *
     * @return array{total:int,queued:int,skipped:int,last_delay_seconds:int}
     */
    public function enqueue(User $actor): array
    {
        abort_unless(self::canRequest($actor), 403);

        $connection = FingerprintQueue::CONNECTION;
        $driver = config("queue.connections.{$connection}.driver");

        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new RuntimeException('Reboot All membutuhkan koneksi fingerprint dengan queue driver asinkron.');
        }

        $lock = Cache::lock('fingerprint-reboot-all:dispatch', 120);

        if (! $lock->get()) {
            throw new RuntimeException('Permintaan Reboot All lainnya sedang diproses.');
        }

        try {
            $plan = $this->plan();

            if ($plan['eligible'] === 0) {
                throw new RuntimeException('Tidak ada mesin fingerprint dengan alamat IPv4 management yang valid.');
            }

            // Also block repeat clicks while previously scheduled jobs are pending.
            $cooldownSeconds = self::delaySeconds($plan['eligible'] - 1) + 600;

            if (! Cache::add('fingerprint-reboot-all:pending', $actor->getKey(), now()->addSeconds($cooldownSeconds))) {
                throw new RuntimeException('Reboot All sudah dijadwalkan. Tunggu hingga rangkaian sebelumnya selesai.');
            }

            $queued = 0;

            try {
                app(ActivityLogger::class)->log(
                    'device.fingerprint_reboot_all_requested',
                    null,
                    [],
                    [
                        'eligible' => $plan['eligible'],
                        'skipped' => $plan['skipped'],
                        'spacing_seconds' => self::SPACING_SECONDS,
                    ],
                    'Penjadwalan Reboot All fingerprint dimulai.',
                    (int) $actor->getKey(),
                );

                foreach ($plan['ids'] as $deviceId) {
                    RestartFingerprintDevice::dispatch(
                        $deviceId,
                        (int) $actor->getKey(),
                        'bulk',
                    )->onQueue(FingerprintQueue::NAME)
                        ->delay(now()->addSeconds(self::delaySeconds($queued)));

                    $queued++;
                }
            } catch (Throwable $exception) {
                // Keep the cooldown if there was a partial dispatch, to avoid
                // silently scheduling duplicate reboot commands.
                if ($queued === 0) {
                    Cache::forget('fingerprint-reboot-all:pending');
                }

                Log::error('Sebagian atau seluruh pekerjaan Reboot All gagal diantrikan.', [
                    'requested_by' => $actor->getKey(),
                    'queued' => $queued,
                    'expected' => $plan['eligible'],
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            return [
                'total' => $plan['total'],
                'queued' => $queued,
                'skipped' => $plan['skipped'],
                'last_delay_seconds' => self::delaySeconds($queued - 1),
            ];
        } finally {
            $lock->release();
        }
    }
}
