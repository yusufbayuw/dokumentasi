<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NetworkProbeLocation;
use App\Models\NetworkQualityMeasurement;
use App\Models\NetworkQualityTarget;
use App\NetworkMonitoring\QualityClassifier;
use App\NetworkMonitoring\RemoteProbeValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkProbeReportController extends Controller
{
    public function __invoke(
        Request $request,
        RemoteProbeValidator $guard,
        QualityClassifier $classifier,
    ): JsonResponse {
        abort_unless(config('network_quality.enabled', false)
            && config('network_quality.remote.enabled', false), 404);

        /** @var NetworkProbeLocation $location */
        $location = $request->attributes->get('network_probe_location');
        abort_unless($location instanceof NetworkProbeLocation && $location->enabled, 401);

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'in:'.$location->id],
            'report_id' => ['required', 'uuid'],
            'checked_at' => ['required', 'date'],
            'target_id' => ['required', 'integer'],
            'probe_type' => ['required', 'in:icmp,dns,tcp,https'],
            'address' => ['required', 'string', 'max:512'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['required', 'in:up,down,error'],
            'samples_sent' => ['nullable', 'integer', 'min:0', 'max:10'],
            'samples_received' => ['nullable', 'integer', 'min:0', 'max:10'],
            'latency_ms' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'packet_loss_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rtt_variation_ms' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'response_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'error_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
        ]);

        $checkedAt = Carbon::parse($data['checked_at']);
        if (! $guard->fresh($checkedAt, now())) {
            return response()->json(['message' => 'Report timestamp outside accepted window.'], 422);
        }

        $target = NetworkQualityTarget::find($data['target_id']);
        if (! $target || ! $guard->authorizedTarget($target, $data)) {
            return response()->json(['message' => 'Target is not approved for this report.'], 422);
        }

        if (isset($data['samples_sent'], $data['samples_received'])
            && $data['samples_received'] > $data['samples_sent']) {
            return response()->json(['message' => 'Invalid sample counts.'], 422);
        }

        $existing = NetworkQualityMeasurement::query()
            ->where('external_report_id', $data['report_id'])->first();

        if ($existing) {
            if ($existing->probe_location_id !== $location->id) {
                return response()->json(['message' => 'Conflicting report id.'], 409);
            }

            return response()->json(['message' => 'Already received.'], 200);
        }

        // A remote report is authenticated but never allowed to invent a
        // target, address, method or ISP. Those are resolved server-side.
        NetworkQualityMeasurement::create([
            'target_id' => $target->id,
            'probe_location_id' => $location->id,
            'external_report_id' => $data['report_id'],
            'monitor_key' => 'target:'.$target->id.'@probe:'.$location->id,
            'target_name' => $target->name,
            'address' => $target->address,
            'probe_type' => $target->probe_type,
            'probe_name' => $location->name,
            'isp_name' => $location->isp_name,
            'status' => $data['status'],
            'quality' => $classifier->classify($data),
            'samples_sent' => $data['samples_sent'] ?? null,
            'samples_received' => $data['samples_received'] ?? null,
            'latency_ms' => $data['latency_ms'] ?? null,
            'packet_loss_pct' => $data['packet_loss_pct'] ?? null,
            'rtt_variation_ms' => $data['rtt_variation_ms'] ?? null,
            'response_code' => $data['response_code'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'error_code' => $data['error_code'] ?? null,
            'checked_at' => $checkedAt,
        ]);

        $location->update(['last_seen_at' => now()]);

        return response()->json(['message' => 'Accepted.'], 202);
    }
}
