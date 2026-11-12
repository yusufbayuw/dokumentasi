<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


// Trusted FreeRADIUS adapter only. Authentication is via a strong service key,
// not the interactive Laravel/Filament session.
Route::post('/nac/radius/authorize', \App\Http\Controllers\Api\NacRadiusAuthorizationController::class)
    ->middleware(\App\Http\Middleware\AuthenticateNacRadius::class);


// Remote probe reports are authenticated with a per-location secret, not
// interactive Filament sessions. The feature is independently opt-in.
Route::post('/network-quality/probe-results', \App\Http\Controllers\Api\NetworkProbeReportController::class)
    ->middleware([
        'throttle:30,1',
        \App\Http\Middleware\AuthenticateNetworkProbe::class,
    ]);
