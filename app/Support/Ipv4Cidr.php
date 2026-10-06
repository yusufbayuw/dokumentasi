<?php

namespace App\Support;

use InvalidArgumentException;

final class Ipv4Cidr
{
    private function __construct(
        public readonly string $inputAddress,
        public readonly int $prefix,
        public readonly string $netmask,
        public readonly string $network,
        public readonly string $broadcast,
        public readonly string $hostMin,
        public readonly string $hostMax,
        public readonly int $hostCount,
    ) {
    }

    public static function from(string $address, int $prefix): self
    {
        $rawIp = ip2long($address);

        if ($rawIp === false || $prefix < 1 || $prefix > 32) {
            throw new InvalidArgumentException('IPv4/CIDR tidak valid.');
        }

        $ipLong = (int) sprintf('%u', $rawIp);
        $netmaskLong = $prefix === 0
            ? 0
            : ((0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF);
        $networkLong = $ipLong & $netmaskLong;
        $broadcastLong = $networkLong | (~$netmaskLong & 0xFFFFFFFF);

        if ($prefix <= 30) {
            $hostMinLong = $networkLong + 1;
            $hostMaxLong = $broadcastLong - 1;
            $hostCount = max(0, ($hostMaxLong - $hostMinLong) + 1);
        } else {
            $hostMinLong = $networkLong;
            $hostMaxLong = $broadcastLong;
            $hostCount = ($broadcastLong - $networkLong) + 1;
        }

        return new self(
            inputAddress: $address,
            prefix: $prefix,
            netmask: long2ip($netmaskLong),
            network: long2ip($networkLong),
            broadcast: long2ip($broadcastLong),
            hostMin: long2ip($hostMinLong),
            hostMax: long2ip($hostMaxLong),
            hostCount: $hostCount,
        );
    }

    public function overlaps(self $other): bool
    {
        $start = (int) sprintf('%u', ip2long($this->network));
        $end = (int) sprintf('%u', ip2long($this->broadcast));
        $otherStart = (int) sprintf('%u', ip2long($other->network));
        $otherEnd = (int) sprintf('%u', ip2long($other->broadcast));

        return $start <= $otherEnd && $otherStart <= $end;
    }

    public function contains(string $ip): bool
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        $value = (int) sprintf('%u', $ipLong);
        $start = (int) sprintf('%u', ip2long($this->hostMin));
        $end = (int) sprintf('%u', ip2long($this->hostMax));

        return $value >= $start && $value <= $end;
    }
}
