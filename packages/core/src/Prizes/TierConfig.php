<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TierConfig
{
    private const KEY = 'tier_config';

    /** Tier biasa yang diundi per kotak. Hadiah utama ditaruh terpisah (GameControls). */
    public const TIERS = ['poin', 'voucher', 'merch', 'produk'];

    /**
     * Bobot tier default (spec §5): poin 65 / voucher 32 / merch 3. Produk (hadiah
     * produk gratis, ditambahkan 2026-10-16) mulai 0 sampai Game Admin mengisinya.
     */
    public static function default(): array
    {
        return ['poin' => 65, 'voucher' => 32, 'merch' => 3, 'produk' => 0];
    }

    /**
     * Key settings dipisah per brand supaya override satu brand tidak bocor ke brand lain.
     * Bobot pelanggan lama memakai key lama (tier_config:<brand>), pelanggan baru punya
     * key sendiri (tier_config:<brand>:baru).
     */
    private static function key(?string $brand = null, ?string $segment = null): string
    {
        if ($brand === null) {
            try {
                $brand = (string) config('app.brand', 'amura');
            } catch (\Throwable) {
                $brand = 'amura';
            }
        }

        return self::KEY.':'.$brand.($segment === Segment::BARU ? ':'.Segment::BARU : '');
    }

    /**
     * Bobot aktif untuk satu jenis pelanggan: override admin dari tabel settings, kalau
     * tidak ada pakai default. Pelanggan baru yang belum diatur ikut bobot pelanggan lama.
     */
    public static function current(?string $segment = null, ?string $brand = null): array
    {
        if ($segment === Segment::BARU && ! self::hasOwn(Segment::BARU, $brand)) {
            $segment = Segment::LAMA;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return self::default();
            }
            $raw = DB::table('settings')->where('key', self::key($brand, $segment))->value('value');
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
            // Bobot yang disimpan sebelum tier produk ada tidak punya kunci ini.
            'produk' => (int) ($stored['produk'] ?? 0),
        ];
    }

    /** Apakah jenis pelanggan ini punya bobot sendiri (bukan ikut bawaan). */
    public static function hasOwn(string $segment, ?string $brand = null): bool
    {
        try {
            return Schema::hasTable('settings')
                && self::valid(json_decode((string) DB::table('settings')->where('key', self::key($brand, $segment))->value('value'), true));
        } catch (\Throwable) {
            return false;
        }
    }

    /** Simpan bobot hasil edit admin ke tabel settings, ter-scope brand dan jenis pelanggan. */
    public static function store(array $weights, ?string $brand = null, ?string $segment = null): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand, $segment)],
            ['value' => json_encode([
                'poin' => (int) $weights['poin'],
                'voucher' => (int) $weights['voucher'],
                'merch' => (int) $weights['merch'],
                'produk' => (int) ($weights['produk'] ?? 0),
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
        if (array_key_exists('produk', $value) && (! is_int($value['produk']) || $value['produk'] < 0)) {
            return false;
        }

        // Total 0 membuat undian tidak mungkin jalan: anggap tidak valid, pakai default.
        return array_sum(array_map(fn ($tier) => $value[$tier] ?? 0, self::TIERS)) > 0;
    }
}
