<?php

namespace App\Support;

use App\Models\Device;
use RuntimeException;

final class FingerprintDevice
{
    public static function isSupported(Device $device): bool
    {
        return str_contains(mb_strtolower((string) $device->type?->nama), 'finger');
    }

    public static function address(Device $device): string
    {
        if (! self::isSupported($device)) {
            throw new RuntimeException('Perangkat ini bukan mesin fingerprint.');
        }

        $ip = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;

        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new RuntimeException('Alamat IPv4 management fingerprint belum valid.');
        }

        return $ip;
    }
}
