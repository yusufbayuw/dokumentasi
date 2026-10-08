<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NacAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NacRadiusAuthorizationController extends Controller
{
    public function __invoke(Request $request, NacAuthorizationService $service): JsonResponse
    {
        $data = $request->validate([
            'ssid' => ['required', 'string', 'max:255'],
            'certificate_issuer' => ['required', 'string', 'max:512'],
            'certificate_serial' => ['required', 'string', 'max:255'],
            'tls_verified' => ['required', 'boolean'],
            'nas_identifier' => ['nullable', 'string', 'max:255'],
            'calling_station_id' => ['nullable', 'string', 'max:255'],
        ]);

        $decision = $service->decide($data);

        if (! $decision['allowed']) {
            return response()->json(['error' => 'access_denied', 'reason' => $decision['reason']], 403);
        }

        // FreeRADIUS 3.x rlm_rest reply attributes. Never set Auth-Type=Accept
        // here: cryptographic EAP-TLS verification remains FreeRADIUS' duty.
        return response()->json([
            'reply:Tunnel-Type' => 'VLAN',
            'reply:Tunnel-Medium-Type' => 'IEEE-802',
            'reply:Tunnel-Private-Group-Id' => (string) $decision['vlan_id'],
        ]);
    }
}
