<?php

namespace App\Http\Middleware;

use App\Models\NetworkProbeLocation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateNetworkProbe
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('network_quality.enabled', false)
            || ! config('network_quality.remote.enabled', false)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $id = filter_var($request->input('location_id'), FILTER_VALIDATE_INT);
        $token = $request->bearerToken();

        if (! $id || $id < 1 || ! $token || strlen($token) < 32 || strlen($token) > 256) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $location = NetworkProbeLocation::query()->whereKey($id)->where('enabled', true)->first();

        if (! $location || ! $location->token_hash
            || ! hash_equals((string) $location->token_hash, hash('sha256', $token))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->attributes->set('network_probe_location', $location);

        return $next($request);
    }
}
