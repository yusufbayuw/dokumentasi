<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDeviceCredentials extends Command
{
    protected $signature = 'dokumentasi:migrate-device-credentials
        {--dry-run : Hitung kredensial legacy tanpa mengubah data}';

    protected $description = 'Pindahkan kredensial perangkat legacy ke penyimpanan terenkripsi dan kosongkan plaintext';

    public function handle(): int
    {
        if (blank(config('app.key'))) {
            $this->error('APP_KEY wajib tersedia sebelum kredensial dapat dienkripsi.');

            return self::FAILURE;
        }

        $query = Device::query()
            ->where(function ($query): void {
                $query->whereNotNull('username')
                    ->orWhereNotNull('password');
            });

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Ditemukan {$count} perangkat dengan kredensial legacy.");

            return self::SUCCESS;
        }

        $migrated = 0;

        $query->orderBy('id')->chunkById(100, function ($devices) use (&$migrated): void {
            foreach ($devices as $device) {
                DB::transaction(function () use ($device, &$migrated): void {
                    $credential = DeviceCredential::query()->firstOrNew([
                        'device_id' => $device->id,
                        'credential_type' => 'management',
                    ]);

                    if (filled($device->username)) {
                        $credential->username = (string) $device->username;
                    }

                    if (filled($device->password)) {
                        $credential->secret = (string) $device->password;
                        $credential->rotated_at = now();
                    }

                    $credential->save();

                    Device::query()
                        ->whereKey($device->id)
                        ->update([
                            'username' => null,
                            'password' => null,
                        ]);

                    $migrated++;
                });
            }
        });

        $this->info("{$migrated} perangkat berhasil dipindahkan ke credential store terenkripsi.");

        return self::SUCCESS;
    }
}
