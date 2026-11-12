<?php

namespace App\Console;

use App\Jobs\PingIpAddress;
use App\Jobs\DispatchNetworkQuality;
use App\Jobs\DispatchSnmpPolling;
use App\Jobs\AnalyzeNetworkIncidents;
use App\Jobs\BuildNetworkSlaDaily;
use Illuminate\Support\Facades\DB;
use App\Jobs\RestartFingerprintDevice;
use App\Support\FingerprintQueue;
use App\Models\Device;
use App\Models\DeviceMonitoringCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            $retentionDays = max(7, (int) config('nac.auth_history_retention_days', 90));
            \App\Models\NacAuthEvent::query()
                ->where('occurred_at', '<', now()->subDays($retentionDays))
                ->delete();
        })
            ->name('prune-nac-auth-history')
            ->dailyAt('02:40')
            ->withoutOverlapping(30);

        $minutes = max(1, min(60, (int) config('cmdb.monitoring.schedule_minutes', 5)));

        $schedule->job(new PingIpAddress())
            ->cron("*/{$minutes} * * * *")
            ->name('monitor-infrastructure')
            ->withoutOverlapping(10);

        // The new quality engine is opt-in and never mutates legacy ICMP health.
        $schedule->job(new DispatchNetworkQuality(), 'network-quality')
            ->everyMinute()
            ->name('monitor-network-quality')
            ->withoutOverlapping(5)
            ->when(fn (): bool => (bool) config('network_quality.enabled', false));

        $schedule->job(new DispatchSnmpPolling(), 'network-quality')
            ->everyMinute()
            ->name('monitor-network-snmp')
            ->withoutOverlapping(5)
            ->when(fn (): bool => (bool) config('network_quality.enabled', false)
                && (bool) config('network_quality.snmp.enabled', false));

        // Phase 6: incident evaluation and observed availability aggregation.
        // Both are opt-in and run through the existing network-quality worker.
        $schedule->job(new AnalyzeNetworkIncidents(), 'network-quality')
            ->everyFiveMinutes()
            ->name('analyze-network-incidents')
            ->withoutOverlapping(10)
            ->when(fn (): bool => (bool) config('network_quality.enabled', false)
                && (bool) config('network_quality.incidents.enabled', false));

        $schedule->job(new BuildNetworkSlaDaily(), 'network-quality')
            ->hourly()
            ->name('build-network-sla-observed')
            ->withoutOverlapping(30)
            ->when(fn (): bool => (bool) config('network_quality.enabled', false)
                && (bool) config('network_quality.incidents.enabled', false));

        $schedule->call(function (): void {
            $days = max(7, (int) config('network_quality.retention_days', 14));
            DB::table('network_quality_measurements')
                ->where('checked_at', '<', now()->subDays($days))
                ->delete();

            // Retain new SNMP samples on the same bounded history window.
            if (config('network_quality.snmp.enabled', false)) {
                DB::table('network_interface_samples')
                    ->where('checked_at', '<', now()->subDays($days))
                    ->delete();
            }
        })
            ->name('prune-network-quality')
            ->dailyAt('02:35')
            ->withoutOverlapping(30)
            ->when(fn (): bool => (bool) config('network_quality.enabled', false));

        $schedule->call(function (): void {
            Device::query()
                ->where('auto_reboot_enabled', true)
                ->whereHas('type', fn ($query) => $query->where('nama', 'like', '%Finger%'))
                ->where(function ($query): void {
                    $query->whereNotNull('ip_address_id')
                        ->orWhereHas('interfaces', fn ($interfaceQuery) => $interfaceQuery
                            ->where('is_management', true)
                            ->whereNotNull('ip_address_id'));
                })
                ->pluck('id')
                ->each(fn (int $deviceId) => RestartFingerprintDevice::dispatch(
                    $deviceId,
                    null,
                    'scheduled',
                )->onQueue(FingerprintQueue::NAME));
        })
            ->name('restart-opted-in-fingerprint-devices')
            ->dailyAt('03:00')
            ->withoutOverlapping(30);

        $schedule->call(function (): void {
            $retentionDays = max(7, (int) config('cmdb.monitoring.history_retention_days', 90));

            DeviceMonitoringCheck::query()
                ->where('checked_at', '<', now()->subDays($retentionDays))
                ->delete();
        })
            ->name('prune-monitoring-history')
            ->dailyAt('02:30')
            ->withoutOverlapping(30);
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
