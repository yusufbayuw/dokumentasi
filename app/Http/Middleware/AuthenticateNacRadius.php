<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateNacRadius
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('nac.radius_api_key');
        $provided = $request->header('X-NAC-Radius-Key');

        if (! is_string($key) || strlen($key) < 32 ||
            ! is_string($provided) || ! hash_equals($key, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $allowedIps = config('nac.radius_allowed_ips', []);

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
