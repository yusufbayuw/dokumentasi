<?php

use App\Models\User;
use App\Services\MikroTikHotspotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No public captive endpoints. Only a server operator can explicitly execute this.
Artisan::command('hotspot:connect-user {userId} {mac} {ip} {--confirm}', function (MikroTikHotspotService $hotspot) {
    if (! $this->option('confirm')) {
        $this->error('Perintah ini mengubah router; sertakan --confirm setelah memeriksa host di RouterOS.');
        return 1;
    }

    $userId = (string) $this->argument('userId');
    if (! ctype_digit($userId) || (int) $userId < 1) {
        $this->error('ID pengguna tidak valid.');
        return 1;
    }

    $user = User::query()->find((int) $userId);
    if (! $user || ! $user->is_active) {
        $this->error('Akun pengguna tidak aktif atau tidak ditemukan.');
        return 1;
    }

    try {
        $hotspot->connect($user, (string) $this->argument('mac'), (string) $this->argument('ip'));
    } catch (\Throwable $exception) {
        // Do not print HTTP response payloads: RouterOS might expose credentials.
        $this->error('Hotspot gagal atau ditolak. Verifikasi konfigurasi, host, sertifikat, dan log RouterOS.');
        return 1;
    }

    $this->info('Perintah login Hotspot diterima router untuk pengguna '.$user->id.'.');
    return 0;
})->purpose('Operator-only, opt-in login Hotspot via RouterOS HTTPS REST');
