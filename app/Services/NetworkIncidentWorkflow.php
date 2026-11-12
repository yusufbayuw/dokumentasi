<?php

namespace App\Services;

use App\Models\NetworkIncident;
use Illuminate\Support\Facades\DB;

class NetworkIncidentWorkflow
{
    public function acknowledge(int $incidentId, int $actorId): void
    {
        DB::transaction(function () use ($incidentId, $actorId): void {
            $incident = NetworkIncident::query()->lockForUpdate()->findOrFail($incidentId);
            if ($incident->status !== 'open') {
                return;
            }

            $incident->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $actorId,
                'acknowledged_at' => now(),
            ]);
            $incident->events()->create([
                'event_type' => 'acknowledged',
                'actor_id' => $actorId,
                'message' => 'Insiden telah diterima untuk pemeriksaan.',
            ]);
        });
    }

    public function resolve(int $incidentId, int $actorId): void
    {
        DB::transaction(function () use ($incidentId, $actorId): void {
            $incident = NetworkIncident::query()->lockForUpdate()->findOrFail($incidentId);
            if ($incident->status === 'resolved') {
                return;
            }

            $incident->update(['status' => 'resolved', 'resolved_at' => now()]);
            $incident->events()->create([
                'event_type' => 'resolved_manually',
                'actor_id' => $actorId,
                'message' => 'Ditandai selesai secara manual. Akan dibuka kembali bila indikator buruk berulang.',
            ]);
        });
    }
}
