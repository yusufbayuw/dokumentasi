<?php

namespace App\Jobs;

use App\NetworkMonitoring\ObservedAvailability;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class BuildNetworkSlaDaily implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.incidents.enabled', false)) {
            return;
        }

        // Recalculate yesterday and today to capture late-arriving reports.
        $groups = DB::table('network_quality_measurements')
            ->where('checked_at', '>=', now()->subDay()->startOfDay())
            ->selectRaw('DATE(checked_at) AS report_date, monitor_key,
                MAX(device_id) AS device_id, MAX(target_id) AS target_id,
                COUNT(*) AS total_samples,
                SUM(CASE WHEN status = \'up\' THEN 1 ELSE 0 END) AS up_samples,
                SUM(CASE WHEN status = \'down\' THEN 1 ELSE 0 END) AS down_samples,
                SUM(CASE WHEN status = \'error\' THEN 1 ELSE 0 END) AS error_samples,
                SUM(CASE WHEN quality = \'good\' THEN 1 ELSE 0 END) AS good_samples,
                SUM(CASE WHEN quality = \'degraded\' THEN 1 ELSE 0 END) AS degraded_samples,
                SUM(CASE WHEN quality = \'bad\' THEN 1 ELSE 0 END) AS bad_samples')
            ->groupBy(DB::raw('DATE(checked_at)'), 'monitor_key')
            ->orderBy('report_date')
            ->limit(5000)
            ->get();

        $now = now();
        $records = $groups->map(fn ($item): array => [
            'report_date' => $item->report_date,
            'monitor_key' => $item->monitor_key,
            'device_id' => $item->device_id,
            'target_id' => $item->target_id,
            'total_samples' => $item->total_samples,
            'up_samples' => $item->up_samples,
            'down_samples' => $item->down_samples,
            'error_samples' => $item->error_samples,
            'good_samples' => $item->good_samples,
            'degraded_samples' => $item->degraded_samples,
            'bad_samples' => $item->bad_samples,
            'observed_availability_pct' => ObservedAvailability::percent(
                (int) $item->up_samples, (int) $item->down_samples,
            ),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($records !== []) {
            DB::table('network_sla_daily')->upsert(
                $records,
                ['report_date', 'monitor_key'],
                [
                    'device_id', 'target_id', 'total_samples',
                    'up_samples', 'down_samples', 'error_samples',
                    'good_samples', 'degraded_samples', 'bad_samples',
                    'observed_availability_pct', 'updated_at',
                ],
            );
        }
    }
}
