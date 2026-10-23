<?php

namespace LoyaltyNew\Core;

use Illuminate\Support\ServiceProvider;
use LoyaltyNew\Core\Console\DemoSetupCommand;
use LoyaltyNew\Core\Console\ExpireClaimsCommand;
use LoyaltyNew\Core\Console\ExpirePointsCommand;
use LoyaltyNew\Core\Console\LaunchCatalogCommand;
use LoyaltyNew\Core\Console\SmokeSeedCommand;
use LoyaltyNew\Core\Gate\CachedOrderStatusSource;
use LoyaltyNew\Core\Gate\DemoOrderStatusSource;
use LoyaltyNew\Core\Gate\FairtrackSqliteOrderStatusSource;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\MarkomHttpOrderStatusSource;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use LoyaltyNew\Core\Gate\SoscomOrderStatusSource;
use LoyaltyNew\Core\Gate\StubOrderGate;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/markom.php', 'markom');
        $this->mergeConfigFrom(__DIR__.'/../config/loyalty.php', 'loyalty');

        // Sumber status order: API Soscom, HTTP Markom, atau FairTrack SQLite.
        $this->app->bind(OrderStatusSource::class, function () {
            $driver = (string) config('markom.driver', 'soscom_api');

            // Daftar resi dummy hanya untuk server demo, tidak pernah di produksi.
            if ($driver === 'demo' && $this->app->environment('production')) {
                throw new \RuntimeException('MARKOM_DRIVER=demo tidak boleh dipakai saat APP_ENV=production.');
            }

            $source = match ($driver) {
                'demo' => new DemoOrderStatusSource((string) config('markom.demo.file', '')),
                'fairtrack_sqlite' => new FairtrackSqliteOrderStatusSource(
                    (string) config('markom.fairtrack.database', ''),
                    (string) config('markom.fairtrack.orders_table', 'orders'),
                ),
                'soscom_api' => new SoscomOrderStatusSource(
                    (string) config('markom.soscom.base_url', ''),
                    config('markom.soscom.api_key'),
                    (int) config('markom.soscom.timeout', 5),
                    (array) config('markom.soscom.fields', []),
                ),
                default => new MarkomHttpOrderStatusSource(
                    (string) config('markom.api.base_url', ''),
                    config('markom.api.token'),
                    (int) config('markom.api.timeout', 5),
                ),
            };

            $ttl = (int) config('markom.cache_ttl', 60);
            if ($ttl > 0) {
                return new CachedOrderStatusSource(
                    $source,
                    $ttl,
                    (string) config('app.brand', 'default'),
                );
            }

            return $source;
        });

        // Gate: 'markom' (gate nyata, default) atau 'stub' (dev/tes). Stub meloloskan
        // resi apa pun, jadi hanya dipakai kalau diminta eksplisit dan tidak pernah di produksi.
        $this->app->bind(OrderGate::class, function () {
            if ((string) config('markom.gate', 'markom') !== 'stub') {
                return new MarkomApiOrderGate($this->app->make(OrderStatusSource::class));
            }

            if ($this->app->environment('production')) {
                throw new \RuntimeException('MARKOM_GATE=stub tidak boleh dipakai saat APP_ENV=production.');
            }

            return new StubOrderGate;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SmokeSeedCommand::class, DemoSetupCommand::class,ExpireClaimsCommand::class, ExpirePointsCommand::class, LaunchCatalogCommand::class]);

            $this->publishes([
                __DIR__.'/../config/markom.php' => config_path('markom.php'),
            ], 'loyalty-config');
        }
    }
}
