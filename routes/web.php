<?php

use App\Http\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app');
});

Route::get('/login', function () {
    return redirect('/app/login');
})->name('login');

Route::get('/laporan', [LaporanController::class, 'generatePDF'])
    ->middleware(['auth', 'can:view_any_device'])
    ->name('laporan');
