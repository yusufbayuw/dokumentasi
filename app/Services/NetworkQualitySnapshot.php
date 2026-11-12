<?php

namespace App\Services;

use App\Models\NetworkQualityMeasurement;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A single recent measurement per monitored device/target, shared by
 * overview stats and drill-down tables so the numbers always agree.
 */
class NetworkQualitySnapshot
{
    public const QUALITIES = ['all', 'good', 'degraded', 'bad', 'unknown'];

    public static function windowMinutes(): int
    {
        return max(15, (int) config('network_quality.interval_minutes', 5) * 3);
    }

    public static function latestIds(): QueryBuilder
    {
        return DB::table('network_quality_measurements')
            ->selectRaw('MAX(id)')
            ->where('checked_at', '>=', now()->subMinutes(self::windowMinutes()))
            ->groupBy('monitor_key');
    }

    public static function query(string $quality = 'all'): EloquentBuilder
    {
        $query = NetworkQualityMeasurement::query()->whereIn('id', self::latestIds());

        if (in_array($quality, self::QUALITIES, true) && $quality !== 'all') {
            $query->where('quality', $quality);
        }

        return $query;
    }

    public static function validQuality(string $quality): string
    {
        return in_array($quality, self::QUALITIES, true) ? $quality : 'all';
    }
}
