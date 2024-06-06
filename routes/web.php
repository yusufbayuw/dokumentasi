<?php

use App\Http\Controllers\DiagramViewController;
use App\Http\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/app');
});
Route::get('/login', function () {
    return redirect('/app/login');
})->name('login');
//Route::get('/diagram', [DiagramViewController::class, 'index'])->name('diagram');
Route::get('/laporan', [LaporanController::class, 'generatePdf'])->middleware('auth');
