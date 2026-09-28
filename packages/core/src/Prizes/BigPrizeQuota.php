<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\PrizeCatalog;

/**
 * Kuota hadiah besar (spec §5: cap tetap per program per brand).
 *
 * Kuota dipotong saat hadiah besar benar-benar terbuka di depan pelanggan,
 * bukan saat sesi dibuat. Pelanggan hanya membuka 6 dari 24 kado, jadi memotong
 * kuota di awal sesi menghabiskan cap untuk hadiah yang tidak pernah ditemukan.
 */
final class BigPrizeQuota
{
    /** Ada sisa kuota untuk katalog ini? Cek biasa, tanpa memotong. */
    public static function available(string $brand, PrizeCatalog $catalog): bool
    {
        $cap = self::cap($brand, $catalog->name);

        return $cap !== null && QuotaGuard::canAward((int) $cap->cap_units, (int) $cap->used_units);
    }

    /** Potong satu unit secara atomik. False kalau kuota sudah habis atau hadiah tanpa cap. */
    public static function reserve(string $brand, GamePrize $prize): bool
    {
        $name = self::catalogName($prize);
        if ($name === null) {
            return false;
        }

        return DB::table('quota_caps')
            ->where('brand', $brand)
            ->where('prize_name', $name)
            ->where('used_units', '<', DB::raw('cap_units'))
            ->increment('used_units') > 0;
    }

    /** Kembalikan satu unit (hadiah besar terbuka tapi tidak diambil). */
    public static function release(string $brand, GamePrize $prize): void
    {
        $name = self::catalogName($prize);
        if ($name === null) {
            return;
        }

        DB::table('quota_caps')
            ->where('brand', $brand)
            ->where('prize_name', $name)
            ->where('used_units', '>', 0)
            ->decrement('used_units');
    }

    private static function cap(string $brand, string $name): ?object
    {
        return DB::table('quota_caps')
            ->where('brand', $brand)
            ->where('prize_name', $name)
            ->first();
    }

    private static function catalogName(GamePrize $prize): ?string
    {
        if (! $prize->prize_catalog_id) {
            return null;
        }

        return PrizeCatalog::whereKey($prize->prize_catalog_id)->value('name');
    }
}
