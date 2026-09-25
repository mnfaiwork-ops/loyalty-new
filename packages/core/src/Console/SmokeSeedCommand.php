<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use LoyaltyNew\Core\Seeding\SmokeSeeder;

/**
 * Isi data dummy untuk SMOKE TEST (spec §9) — semua alur siap dites.
 * Menolak jalan di production. Idempotent.
 *
 *   php artisan loyalty:smoke-seed
 */
final class SmokeSeedCommand extends Command
{
    protected $signature = 'loyalty:smoke-seed';

    protected $description = 'Isi data dummy realistis untuk smoke test semua alur (non-production)';

    public function handle(SmokeSeeder $seeder): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: smoke seed tidak boleh jalan di production.');

            return self::FAILURE;
        }

        $this->info('Mengisi data smoke test…');
        $seeder->run();

        $this->newLine();
        $this->info('Selesai. Kredensial & AWB dummy:');
        $this->table(
            ['Brand', 'Peran', 'Email', 'Password'],
            [
                ['amura', 'cs / game_admin / finance / super_admin', 'cs@amura.local …', SmokeSeeder::PASSWORD],
                ['reglow', 'cs / game_admin / finance / super_admin', 'cs@reglow.local …', SmokeSeeder::PASSWORD],
            ],
        );

        $this->newLine();
        $this->info('AWB + HP dummy untuk main game di /play (kedua brand pakai AWB sendirinya):');
        foreach (SmokeSeeder::playableOrders() as $o) {
            $this->line("  AWB: {$o['awb']}   HP: {$o['phone']}");
        }

        return self::SUCCESS;
    }
}
