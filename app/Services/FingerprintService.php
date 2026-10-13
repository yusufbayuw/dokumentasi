<?php

namespace App\Services;

use App\Models\Device;
use App\Support\FingerprintDevice;
use DateTimeImmutable;
use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;
use Throwable;

class FingerprintService
{
    /**
     * Keep the hardware driver behind one boundary; do not expose raw user
     * responses because rats/zkteco includes the user's device password.
     */
    public function withConnection(Device $device, callable $callback): mixed
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('Ekstensi PHP sockets belum aktif.');
        }

        $zk = new ZKTeco(FingerprintDevice::address($device));

        if ($zk->connect() !== true) {
            throw new RuntimeException('Mesin fingerprint tidak merespons koneksi UDP.');
        }

        try {
            return $callback($zk);
        } finally {
            try {
                $zk->disconnect();
            } catch (Throwable) {
                // The socket may already have been closed by the device.
            }
        }
    }

    public function information(Device $device): array
    {
        return $this->withConnection($device, function (ZKTeco $zk): array {
            $methods = [
                'device_name' => 'deviceName',
                'serial_number' => 'serialNumber',
                'platform' => 'platform',
                'firmware' => 'fmVersion',
                'version' => 'version',
                'os_version' => 'osVersion',
                'pin_width' => 'pinWidth',
            ];

            $result = [];

            foreach ($methods as $key => $method) {
                try {
                    $value = $zk->{$method}();
                    $result[$key] = is_scalar($value) && $value !== false
                        ? mb_substr(trim((string) $value), 0, 255)
                        : null;
                } catch (Throwable) {
                    $result[$key] = null;
                }
            }

            $time = $zk->getTime();
            $result['machine_time'] = is_string($time) ? trim($time) : null;

            return $result;
        });
    }

    public function users(Device $device): array
    {
        return $this->withConnection($device, function (ZKTeco $zk): array {
            $raw = $zk->getUser();

            if (! is_array($raw)) {
                throw new RuntimeException('Respons pengguna fingerprint tidak valid.');
            }

            return array_values(array_filter(array_map(
                [self::class, 'safeUser'],
                $raw,
            )));
        });
    }

    public function attendance(Device $device): array
    {
        return $this->withConnection($device, function (ZKTeco $zk): array {
            $raw = $zk->getAttendance();

            if (! is_array($raw)) {
                throw new RuntimeException('Respons absensi fingerprint tidak valid.');
            }

            return array_values(array_filter(array_map(
                [self::class, 'safeAttendance'],
                $raw,
            )));
        });
    }

    public function syncClock(Device $device): void
    {
        $this->withConnection($device, function (ZKTeco $zk): void {
            if ($zk->setTime(now()->format('Y-m-d H:i:s')) === false) {
                throw new RuntimeException('Mesin menolak perintah sinkronisasi waktu.');
            }
        });
    }

    /** Never retain or show password, card number or biometric templates. */
    public static function safeUser(mixed $raw): ?array
    {
        if (! is_array($raw) || ! isset($raw['userid'])) {
            return null;
        }

        $id = trim((string) $raw['userid']);
        if ($id === '' || mb_strlen($id) > 64) {
            return null;
        }

        $name = trim((string) ($raw['name'] ?? ''));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        return [
            'user_id' => $id,
            'uid' => max(0, (int) ($raw['uid'] ?? 0)),
            'name' => mb_substr($name, 0, 255),
            'role' => (int) ($raw['role'] ?? 0),
        ];
    }

    public static function safeAttendance(mixed $raw): ?array
    {
        if (! is_array($raw) || ! isset($raw['id'], $raw['timestamp'])) {
            return null;
        }

        $userId = trim((string) $raw['id']);
        $date = trim((string) $raw['timestamp']);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date);

        if ($userId === '' || mb_strlen($userId) > 64 ||
            $parsed === false || $parsed->format('Y-m-d H:i:s') !== $date) {
            return null;
        }

        $uid = (int) ($raw['uid'] ?? 0);
        $state = (int) ($raw['state'] ?? 0);
        $type = (int) ($raw['type'] ?? 255);

        return [
            'user_id' => $userId,
            'uid' => $uid,
            'event_at' => $date,
            'state' => $state,
            'type' => $type,
            'event_hash' => hash('sha256', json_encode([$userId, $uid, $date, $state, $type])),
        ];
    }
}
