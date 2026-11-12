<?php

namespace App\Jobs;

use App\Models\NetworkIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NotifyNetworkIncident implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public int $incidentId)
    {
    }

    public function handle(): void
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.incidents.enabled', false)
            || ! config('network_quality.incidents.alerts_enabled', false)) {
            return;
        }

        $recipients = collect(config('network_quality.incidents.alert_emails', []))
            ->filter(fn ($email): bool => is_string($email)
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->take(10)->values()->all();

        if ($recipients === []) {
            return;
        }

        $incident = NetworkIncident::find($this->incidentId);
        if (! $incident) {
            return;
        }

        $message = implode("\n", [
            'Indikasi: '.$incident->target_name,
            'Kategori: '.$incident->kind,
            'Tingkat: '.$incident->severity,
            'Status: '.$incident->status,
            'Saran: '.$incident->correlation_hint,
            'Periksa menu Infrastruktur > Insiden Jaringan untuk verifikasi.',
            'Peringatan ini bersifat indikatif, bukan hasil penetapan akar masalah.',
        ]);

        Mail::raw($message, function ($email) use ($incident, $recipients): void {
            $email->to($recipients)
                ->subject('[Network Quality] '.$incident->severity.' - '.$incident->target_name);
        });
    }
}
