<?php

namespace LoyaltyNew\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Settings\InvalidSettingsFile;
use LoyaltyNew\Core\Settings\SettingsTransfer;

/**
 * Siapkan database server demo saat container start: schema brand, migration,
 * akun admin, dan setting game dari file export produksi (demo/setting-<brand>.json).
 * Aman dijalankan tiap start: data demo hanya dihapus saat DEMO_RESET_KEY diganti.
 *
 *   php artisan loyalty:demo-setup
 *
 * Hanya jalan kalau MARKOM_DRIVER=demo, supaya tidak pernah menghapus DB sungguhan.
 */
final class DemoSetupCommand extends Command
{
    protected $signature = 'loyalty:demo-setup';

    protected $description = 'Siapkan DB server demo (resi dummy), reset kalau DEMO_RESET_KEY berubah';

    public function handle(): int
    {
        if (app()->environment('production') || config('markom.driver') !== 'demo') {
            $this->error('Ditolak: hanya untuk server demo (MARKOM_DRIVER=demo, bukan production).');

            return self::FAILURE;
        }

        $brand = (string) config('app.brand');
        $this->ensureSchema();

        // Reset dipicu dengan mengganti DEMO_RESET_KEY, bukan tiap start: container
        // demo bisa tidur dan bangun lagi di tengah perekaman.
        $key = (string) config('loyalty.demo.reset_key', '');
        $settingKey = 'demo:reset_key:'.$brand;
        $stored = Schema::hasTable('settings') ? DB::table('settings')->where('key', $settingKey)->value('value') : null;
        $fresh = $stored === null || $stored !== $key;

        $this->call($fresh ? 'migrate:fresh' : 'migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);

        if ($fresh || ! PrizeCatalog::where('brand', $brand)->exists()) {
            if (! $this->importSetting($brand)) {
                return self::FAILURE;
            }
        }

        DB::table('settings')->updateOrInsert(
            ['key' => $settingKey],
            ['value' => $key, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->info($fresh ? "Demo {$brand}: data dikosongkan dan setting di-import." : "Demo {$brand}: data lama dipakai.");

        return self::SUCCESS;
    }

    /** Satu schema Postgres per brand, supaya dua app demo bisa berbagi satu DB. */
    private function ensureSchema(): void
    {
        $connection = DB::connection();
        $schema = (string) $connection->getConfig('search_path');
        if ($connection->getDriverName() !== 'pgsql' || $schema === '' || $schema === 'public') {
            return;
        }

        $connection->statement('create schema if not exists "'.str_replace('"', '', $schema).'"');
    }

    private function importSetting(string $brand): bool
    {
        $file = (string) config('loyalty.demo.setting_file', '');
        $data = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;

        try {
            SettingsTransfer::apply($data, $brand);
        } catch (InvalidSettingsFile $e) {
            $this->error("Setting demo tidak bisa di-import dari '{$file}':");
            foreach ($e->errors as $error) {
                $this->line('  - '.$error);
            }

            return false;
        }

        return true;
    }
}
