<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\LoyaltyNew\Core\Gacha\SessionService::class, fn () =>
            new \LoyaltyNew\Core\Gacha\SessionService(
                app(\LoyaltyNew\Core\Gate\OrderGate::class), (string) config('app.brand')
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama bulan/hari di layar pelanggan dalam bahasa Indonesia.
        \Carbon\Carbon::setLocale('id');

        \Illuminate\Support\Facades\View::composer(['play.*', 'customer.*', 'layouts.play'], function ($view) {
            $themes = config('play.themes');
            $view->with('theme', $themes[config('app.brand')] ?? $themes['amura']);
        });
    }
}
