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
