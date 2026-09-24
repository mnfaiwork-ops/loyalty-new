<?php

use App\Http\Controllers\Admin\CsClaimController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/play', [PlayController::class, 'form'])->name('play.form');
Route::post('/play/start', [PlayController::class, 'start'])->name('play.start');
Route::get('/play/{token}', [PlayController::class, 'board'])->name('play.board');
Route::post('/play/{token}/pick', [PlayController::class, 'pick'])->name('play.pick');
Route::post('/play/{token}/reveal', [PlayController::class, 'reveal'])->name('play.reveal');
Route::post('/play/{token}/take', [PlayController::class, 'take'])->name('play.take');

Route::middleware(['auth', 'role:cs,super_admin'])->prefix('admin')->group(function () {
    Route::get('/claims', [CsClaimController::class, 'index'])->name('admin.claims');
    Route::post('/claims/{code}/resolve', [CsClaimController::class, 'resolve'])->name('admin.claims.resolve');
});
