<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TierConfig
{
    private const KEY = 'tier_config';

    /** Bobot tier default (spec §5): poin 65 / voucher 32 / merch 3. */
    public static function default(): array
    {
        return ['poin' => 65, 'voucher' => 32, 'merch' => 3];
    }

    /** Key settings dipisah per brand supaya override satu brand tidak bocor ke brand lain. */
    private static function key(?string $brand = null): string
    {
        if ($brand === null) {
            try {
                $brand = (string) config('app.brand', 'amura');
            } catch (\Throwable) {
                $brand = 'amura';
            }
        }

        return self::KEY.':'.$brand;
    }

    /** Bobot aktif: override admin dari tabel settings, kalau tidak ada pakai default. */
    public static function current(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return self::default();
            }
            $raw = DB::table('settings')->where('key', self::key())->value('value');
        } catch (\Throwable) {
            return self::default();
        }

        $stored = $raw ? json_decode($raw, true) : null;

        if (! self::valid($stored)) {
            return self::default();
        }

        return [
            'poin' => (int) $stored['poin'],
            'voucher' => (int) $stored['voucher'],
            'merch' => (int) $stored['merch'],
        ];
    }

    /** Simpan bobot hasil edit admin ke tabel settings, ter-scope brand. */
    public static function store(array $weights, ?string $brand = null): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand)],
            ['value' => json_encode([
                'poin' => (int) $weights['poin'],
                'voucher' => (int) $weights['voucher'],
                'merch' => (int) $weights['merch'],
            ]), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private static function valid(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach (['poin', 'voucher', 'merch'] as $tier) {
            if (! array_key_exists($tier, $value) || ! is_int($value[$tier]) || $value[$tier] < 0) {
                return false;
            }
        }

        // Total 0 membuat undian tidak mungkin jalan: anggap tidak valid, pakai default.
        return array_sum(array_map(fn ($tier) => $value[$tier], ['poin', 'voucher', 'merch'])) > 0;
    }
}
