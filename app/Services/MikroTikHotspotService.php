<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Explicit, operator-triggered RouterOS v7 Hotspot integration.
 *
 * Deliberately not exposed as an unauthenticated captive-portal endpoint.
 * An end-user login redirect is not proof of a trusted MAC/IP association.
 */
class MikroTikHotspotService
{
    public function connect(User $user, string $macAddress, string $ipAddress): void
    {
        $client = $this->client();

        if (! $user->exists || ! $user->is_active) {
            throw new RuntimeException('Akun pengguna tidak aktif atau tidak ditemukan.');
        }

        $macAddress = strtoupper(trim($macAddress));
        $ipAddress = trim($ipAddress);

        if (! preg_match('/^(?:[A-F0-9]{2}:){5}[A-F0-9]{2}$/', $macAddress)
            || ! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('MAC address atau IPv4 tidak valid.');
        }

        // Never trust a browser-supplied MAC/IP pair without checking RouterOS.
        $hosts = $client->get('/rest/ip/hotspot/host')->throw()->json();
        if (! is_array($hosts) || ! collect($hosts)->contains(
            fn ($host): bool => is_array($host)
                && strtoupper((string) ($host['mac-address'] ?? '')) === $macAddress
                && (string) ($host['address'] ?? '') === $ipAddress
        )) {
            throw new RuntimeException('Perangkat belum terdaftar pada host Hotspot router.');
        }

        $name = 'dokumentasi-'.$user->getKey();
        $comment = 'managed-by=dokumentasi;user-id='.$user->getKey();
        $secret = (string) config('mikrotik_hotspot.user_secret');
        $password = hash_hmac('sha256', 'hotspot-user:'.$user->getKey(), $secret);

        // RouterOS REST GET may include other fields; never log or return the response.
        $accounts = $client->get('/rest/ip/hotspot/user')->throw()->json();
        if (! is_array($accounts)) {
            throw new RuntimeException('Respons daftar pengguna Hotspot tidak valid.');
        }

        $account = collect($accounts)->first(
            fn ($candidate): bool => is_array($candidate) && ($candidate['name'] ?? null) === $name
        );

        if ($account !== null) {
            if (($account['comment'] ?? null) !== $comment
                || ! preg_match('/^\*[A-Za-z0-9]+$/', (string) ($account['.id'] ?? ''))) {
                throw new RuntimeException('Akun Hotspot dengan nama yang sama bukan milik integrasi ini.');
            }

            // Synchronize the credential we own; never overwrite a manually managed user.
            $client->patch('/rest/ip/hotspot/user/'.$account['.id'], [
                'password' => $password,
                'mac-address' => $macAddress,
            ])->throw();
        } else {
            $client->put('/rest/ip/hotspot/user', [
                'name' => $name,
                'password' => $password,
                'profile' => (string) config('mikrotik_hotspot.profile'),
                'mac-address' => $macAddress,
                'comment' => $comment,
            ])->throw();
        }

        // RouterOS v7 console command, accessible via REST POST.
        $client->post('/rest/ip/hotspot/active/login', [
            'user' => $name,
            'password' => $password,
            'mac-address' => $macAddress,
            'ip' => $ipAddress,
        ])->throw();
    }

    private function client(): PendingRequest
    {
        if (! config('mikrotik_hotspot.enabled')) {
            throw new RuntimeException('Integrasi MikroTik Hotspot dinonaktifkan.');
        }

        $url = rtrim((string) config('mikrotik_hotspot.url'), '/');
        $username = (string) config('mikrotik_hotspot.username');
        $password = (string) config('mikrotik_hotspot.password');
        $userSecret = (string) config('mikrotik_hotspot.user_secret');
        $profile = (string) config('mikrotik_hotspot.profile', 'default');
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || blank($username) || strlen($password) < 16 || strlen($userSecret) < 32
            || ! preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $profile)) {
            throw new RuntimeException('Konfigurasi HTTPS/kredensial MikroTik Hotspot tidak lengkap atau tidak aman.');
        }

        $caBundle = config('mikrotik_hotspot.ca_bundle');
        if (filled($caBundle) && (! is_string($caBundle) || ! is_file($caBundle))) {
            throw new RuntimeException('File CA MikroTik tidak ditemukan.');
        }

        // No withoutVerifying(): production requires a trusted router certificate.
        return Http::baseUrl($url)
            ->withBasicAuth($username, $password)
            ->withOptions(['verify' => filled($caBundle) ? $caBundle : true])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(10);
    }
}
