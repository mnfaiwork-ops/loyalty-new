<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use LoyaltyNew\Core\Seeding\LaunchCatalog;

/**
 * Pasang dan terbitkan katalog tukar poin bulan pertama untuk brand ini.
 *
 *   php artisan loyalty:katalog-awal                 # bulan berjalan
 *   php artisan loyalty:katalog-awal --period=2026-11
 */
final class LaunchCatalogCommand extends Command
{
    protected $signature = 'loyalty:katalog-awal {--period= : Bulan YYYY-MM, kosong = bulan berjalan}';

    protected $description = 'Pasang dan terbitkan katalog tukar poin bulan pertama (brand ini saja)';

    public function handle(): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Format bulan harus YYYY-MM, misalnya 2026-11.');

            return self::FAILURE;
        }

        $brand = (string) config('app.brand');
        $result = LaunchCatalog::apply($brand, $period);

        $this->info("Katalog {$brand} {$period} terbit: {$result['active']} item tampil, {$result['hidden']} disembunyikan.");

        return self::SUCCESS;
    }
}
