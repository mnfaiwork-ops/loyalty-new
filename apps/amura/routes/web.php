<?php

use App\Http\Controllers\Admin\CsClaimController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GameAdminController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\CatalogController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\RewardController;
use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;

// Area pelanggan: perangkat diingat 30 hari setelah resi + HP cocok.
Route::middleware('customer')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/hadiah', [RewardController::class, 'index'])->name('rewards');
    Route::get('/hadiah/{code}', [RewardController::class, 'show'])->name('rewards.show');
    Route::post('/hadiah/{code}/redeem', [RewardController::class, 'redeem'])->name('rewards.redeem');
    Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog');
    Route::post('/katalog/{item}/tukar', [CatalogController::class, 'exchange'])->name('catalog.exchange');
});
Route::post('/keluar', [HomeController::class, 'logout'])->name('customer.logout');

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
    Route::get('/claims/{code}', [CsClaimController::class, 'show'])->name('admin.claims.show');
    Route::post('/claims/{code}/confirm', [CsClaimController::class, 'confirm'])->name('admin.claims.confirm');
    Route::post('/claims/{code}/reject', [CsClaimController::class, 'reject'])->name('admin.claims.reject');
    Route::get('/shipping', [ShippingController::class, 'index'])->name('admin.shipping');
    Route::post('/shipping/{code}/ship', [ShippingController::class, 'ship'])->name('admin.shipping.ship');
    Route::post('/shipping/{code}/deliver', [ShippingController::class, 'deliver'])->name('admin.shipping.deliver');
});

Route::middleware(['auth', 'role:game_admin,super_admin'])->prefix('admin')->group(function () {
    Route::get('/game', [GameAdminController::class, 'index'])->name('admin.game');
    Route::post('/game/catalog', [GameAdminController::class, 'storeCatalog'])->name('admin.game.catalog');
    Route::post('/game/tiers', [GameAdminController::class, 'updateTiers'])->name('admin.game.tiers');
});

Route::middleware(['auth', 'role:finance,super_admin'])->prefix('admin')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('admin.finance');
    Route::post('/finance/{code}/approve', [FinanceController::class, 'approve'])->name('admin.finance.approve');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/users', [SuperAdminController::class, 'index'])->name('admin.users');
    Route::post('/users', [SuperAdminController::class, 'store'])->name('admin.users.store');
    Route::post('/users/{id}/role', [SuperAdminController::class, 'role'])->name('admin.users.role');
});
