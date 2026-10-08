<?php

namespace App\Services;

use App\Models\NacAccessPolicy;
use App\Models\NacAuthEvent;
use App\Models\NacCertificate;
use App\Models\NacNetwork;

class NacAuthorizationService
{
    /**
     * Only call this after the trusted RADIUS server has verified EAP-TLS.
     * The issuer and serial are extracted from the verified leaf certificate.
     *
     * @return array{allowed: bool, reason: string, vlan_id: ?int}
     */
    public function decide(array $data): array
    {
        $network = NacNetwork::query()->where('ssid', $data['ssid'])
            ->where('enabled', true)->first();
        $certificate = NacCertificate::query()
            ->with('client.user.roles')
            ->where('issuer', trim($data['certificate_issuer']))
            ->where('serial_number', strtoupper(trim($data['certificate_serial'])))
            ->first();

        $client = $certificate?->client;
        $user = $client?->user;
        $policy = null;
        $reason = 'deny_unverified_tls';

        if ($data['tls_verified'] !== true) {
            $reason = 'deny_unverified_tls';
        } elseif (! $network) {
            $reason = 'deny_network';
        } elseif (! $certificate) {
            $reason = 'deny_unknown_certificate';
        } elseif ($certificate->revoked_at !== null ||
            $certificate->valid_from->isFuture() ||
            ! $certificate->valid_until->isFuture()) {
            $reason = 'deny_certificate_inactive';
        } elseif ($client?->status !== 'active') {
            $reason = 'deny_client_inactive';
        } elseif (! $user || ! $user->is_active) {
            $reason = 'deny_user_inactive';
        } else {
            $policy = NacAccessPolicy::query()
                ->where('nac_network_id', $network->id)
                ->where('enabled', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->first(fn (NacAccessPolicy $candidate): bool =>
                    $candidate->role_name === null || $user->hasRole($candidate->role_name));

            $reason = $policy ? 'allow_policy' : 'deny_no_policy';
        }

        $allowed = $policy !== null;
        $vlan = $allowed ? $policy->vlan_id : null;

        NacAuthEvent::query()->create([
            'nac_network_id' => $network?->id,
            'nac_client_id' => $client?->id,
            'nac_certificate_id' => $certificate?->id,
            'nas_identifier' => $data['nas_identifier'] ?? null,
            'calling_station_id' => $data['calling_station_id'] ?? null,
            'decision' => $allowed ? 'allow' : 'deny',
            'reason' => $reason,
            'vlan_id' => $vlan,
            'occurred_at' => now(),
        ]);

        return ['allowed' => $allowed, 'reason' => $reason, 'vlan_id' => $vlan];
    }
}
