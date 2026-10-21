<?php

use App\Http\Controllers\Admin\CsClaimController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DailyListController;
use App\Http\Controllers\Admin\DisplayController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GameAdminController;
use App\Http\Controllers\Admin\RewardCatalogController;
use App\Http\Controllers\Admin\SettingTransferController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\CatalogController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\PinController;
use App\Http\Controllers\Customer\RewardController;
use App\Http\Controllers\Customer\SignInController;
use App\Http\Controllers\GuideController;
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

// Masuk lagi dengan HP + PIN, dan layar PIN setelah resi + HP cocok.
Route::get('/masuk', [SignInController::class, 'show'])->name('customer.login');
Route::post('/masuk', [SignInController::class, 'store'])->middleware('throttle:pin');
Route::get('/pin', [PinController::class, 'show'])->name('pin.show');
Route::post('/pin', [PinController::class, 'verify'])->middleware('throttle:pin');
Route::get('/pin/buat', [PinController::class, 'create'])->name('pin.create');
Route::post('/pin/buat', [PinController::class, 'store']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Landing page cara main: publik, angka dan hadiah dibaca dari setting game.
Route::get('/cara-main', [GuideController::class, 'show'])->name('guide');

Route::get('/play', [PlayController::class, 'form'])->name('play.form');
// Batas per IP di atas kunci per resi+HP, supaya bot tidak membanjiri API Soscom.
Route::post('/play/start', [PlayController::class, 'start'])->middleware('throttle:30,1')->name('play.start');
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
    Route::get('/customers', [CustomerController::class, 'index'])->name('admin.customers');
    Route::post('/customers/{id}/reset-pin', [CustomerController::class, 'resetPin'])->whereNumber('id')->name('admin.customers.reset-pin');
    Route::get('/daftar-harian', [DailyListController::class, 'index'])->name('admin.daily');
    Route::get('/daftar-harian/{list}.csv', [DailyListController::class, 'download'])->name('admin.daily.download');
});

Route::middleware(['auth', 'role:game_admin,super_admin'])->prefix('admin')->group(function () {
    Route::get('/game', [GameAdminController::class, 'index'])->name('admin.game');
    Route::post('/game/catalog', [GameAdminController::class, 'storeCatalog'])->name('admin.game.catalog');
    Route::post('/game/tiers', [GameAdminController::class, 'updateTiers'])->name('admin.game.tiers');
    Route::post('/game/controls', [GameAdminController::class, 'updateControls'])->name('admin.game.controls');
    Route::post('/game/catalog/{id}', [GameAdminController::class, 'updateCatalog'])->whereNumber('id')->name('admin.game.catalog.update');
    Route::get('/catalog', [RewardCatalogController::class, 'index'])->name('admin.catalog');
    Route::post('/catalog/items', [RewardCatalogController::class, 'storeItem'])->name('admin.catalog.items');
    Route::post('/catalog/items/bulk', [RewardCatalogController::class, 'updateItems'])->name('admin.catalog.items.bulk');
    Route::post('/catalog/items/{item}', [RewardCatalogController::class, 'updateItem'])->name('admin.catalog.items.update');
    Route::post('/catalog/copy', [RewardCatalogController::class, 'copy'])->name('admin.catalog.copy');
    Route::post('/catalog/publish', [RewardCatalogController::class, 'publish'])->name('admin.catalog.publish');
    Route::post('/catalog/settings', [RewardCatalogController::class, 'settings'])->name('admin.catalog.settings');
    Route::get('/tampilan', [DisplayController::class, 'index'])->name('admin.display');
    Route::post('/tampilan/teks', [DisplayController::class, 'updateTexts'])->name('admin.display.texts');
    Route::post('/tampilan/popup', [DisplayController::class, 'updatePopup'])->name('admin.display.popup');
});

Route::middleware(['auth', 'role:finance,super_admin'])->prefix('admin')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('admin.finance');
    Route::post('/finance/{code}/approve', [FinanceController::class, 'approve'])->name('admin.finance.approve');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/users', [SuperAdminController::class, 'index'])->name('admin.users');
    Route::post('/users', [SuperAdminController::class, 'store'])->name('admin.users.store');
    Route::post('/users/{id}/role', [SuperAdminController::class, 'role'])->name('admin.users.role');
    // Backup setting: export/import setting game (hanya Super Admin, keputusan Fai 2026-10-21).
    Route::get('/setting', [SettingTransferController::class, 'index'])->name('admin.setting');
    Route::get('/setting/export', [SettingTransferController::class, 'export'])->name('admin.setting.export');
    Route::post('/setting/import', [SettingTransferController::class, 'preview'])->middleware('throttle:20,1')->name('admin.setting.preview');
    Route::post('/setting/import/terapkan', [SettingTransferController::class, 'apply'])->name('admin.setting.apply');
    Route::get('/setting/backup/{id}', [SettingTransferController::class, 'backup'])->whereNumber('id')->name('admin.setting.backup');
});
