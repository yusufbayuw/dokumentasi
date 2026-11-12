<?php

namespace App\Jobs;

use App\Models\NetworkIncident;
use App\Models\NetworkQualityMeasurement;
use App\NetworkMonitoring\NetworkIncidentCorrelation;
use App\NetworkMonitoring\NetworkIncidentRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AnalyzeNetworkIncidents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(NetworkIncidentRules $rules, NetworkIncidentCorrelation $correlation): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.incidents.enabled', false)) {
            return;
        }

        $window = (int) config('network_quality.incidents.analysis_window_minutes', 60);
        $max = (int) config('network_quality.incidents.max_monitors_per_cycle', 500);
        $samples = max(
            (int) config('network_quality.incidents.bad_samples_to_open', 3),
            (int) config('network_quality.incidents.good_samples_to_resolve', 2),
        );

        $keys = NetworkQualityMeasurement::query()
            ->where('checked_at', '>=', now()->subMinutes($window))
            ->select('monitor_key')->distinct()->orderBy('monitor_key')
            ->limit($max)->pluck('monitor_key');

        foreach ($keys as $key) {
            $latest = NetworkQualityMeasurement::query()
                ->where('monitor_key', $key)
                ->where('checked_at', '>=', now()->subMinutes($window))
                ->orderByDesc('checked_at')->orderByDesc('id')
                ->limit($samples)->get();

            $decision = $rules->decide($latest);
            if ($decision === null || $latest->isEmpty()) {
                continue;
            }

            $newest = $latest->first();
            $incidentId = DB::transaction(function () use ($key, $newest, $latest, $decision, $correlation): ?int {
                $incidentKey = sha1('network-quality:'.$key);
                $incident = NetworkIncident::query()
                    ->where('incident_key', $incidentKey)
                    ->lockForUpdate()->first();

                if ($decision['action'] === 'resolve') {
                    if (! $incident || $incident->status === 'resolved') {
                        return null;
                    }

                    $incident->update([
                        'status' => 'resolved',
                        'last_seen_at' => $newest->checked_at,
                        'resolved_at' => now(),
                    ]);
                    $incident->events()->create([
                        'event_type' => 'resolved',
                        'message' => 'Dua atau lebih pengukuran berurutan telah kembali baik.',
                    ]);

                    return null; // Successful recovery should not trigger an alert.
                }

                $hint = $correlation->hint($newest);
                $shouldNotify = false;

                if (! $incident) {
                    $incident = NetworkIncident::create([
                        'incident_key' => $incidentKey,
                        'monitor_key' => $key,
                        'device_id' => $newest->device_id,
                        'target_id' => $newest->target_id,
                        'target_name' => $newest->target_name,
                        'kind' => $decision['kind'],
                        'severity' => $decision['severity'],
                        'status' => 'open',
                        'correlation_hint' => $hint,
                        'started_at' => $latest->last()->checked_at,
                        'last_seen_at' => $newest->checked_at,
                    ]);
                    $incident->events()->create([
                        'event_type' => 'opened',
                        'message' => 'Indikasi gangguan berulang memenuhi ambang sistem.',
                    ]);
                    $shouldNotify = true;
                } elseif ($incident->status === 'resolved') {
                    $incident->update([
                        'kind' => $decision['kind'],
                        'severity' => $decision['severity'],
                        'status' => 'open',
                        'correlation_hint' => $hint,
                        'started_at' => $latest->last()->checked_at,
                        'last_seen_at' => $newest->checked_at,
                        'resolved_at' => null,
                        'acknowledged_at' => null,
                        'acknowledged_by' => null,
                    ]);
                    $incident->events()->create([
                        'event_type' => 'reopened',
                        'message' => 'Indikasi gangguan kembali setelah insiden sebelumnya selesai.',
                    ]);
                    $shouldNotify = true;
                } elseif ($decision['severity'] === 'critical' && $incident->severity !== 'critical') {
                    $incident->update([
                        'kind' => $decision['kind'],
                        'severity' => 'critical',
                        'status' => 'open',
                        'correlation_hint' => $hint,
                        'last_seen_at' => $newest->checked_at,
                        'acknowledged_at' => null,
                        'acknowledged_by' => null,
                    ]);
                    $incident->events()->create([
                        'event_type' => 'escalated',
                        'message' => 'Indikasi gangguan meningkat ke kritis.',
                    ]);
                    $shouldNotify = true;
                } else {
                    $incident->update(['last_seen_at' => $newest->checked_at]);
                }

                // Only opening/reopening/escalation should send an alert.
                return $shouldNotify ? $incident->id : null;
            });

            if ($incidentId && config('network_quality.incidents.alerts_enabled', false)) {
                NotifyNetworkIncident::dispatch($incidentId)->onQueue('default');
            }
        }
    }
}
