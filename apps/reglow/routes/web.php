<?php

use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/play', [PlayController::class, 'form'])->name('play.form');
Route::post('/play/start', [PlayController::class, 'start'])->name('play.start');
Route::get('/play/{token}', [PlayController::class, 'board'])->name('play.board');
Route::post('/play/{token}/pick', [PlayController::class, 'pick'])->name('play.pick');
Route::post('/play/{token}/reveal', [PlayController::class, 'reveal'])->name('play.reveal');
Route::post('/play/{token}/take', [PlayController::class, 'take'])->name('play.take');
