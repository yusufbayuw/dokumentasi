<?php

namespace App\NetworkMonitoring;

class ObservedAvailability
{
    public static function percent(int $up, int $down): ?float
    {
        if ($up < 0 || $down < 0 || ($up + $down) < 1) {
            return null;
        }

        return round(100 * $up / ($up + $down), 2);
    }
}
