<?php

namespace LoyaltyNew\Core\Prizes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\PrizeCatalog;

/**
 * Kuota hadiah besar (spec §5: cap tetap per program per brand).
 *
 * Kuota dipotong saat hadiah besar benar-benar terbuka di depan pelanggan,
 * bukan saat sesi dibuat. Pelanggan hanya membuka 6 dari 24 kado, jadi memotong
 * kuota di awal sesi menghabiskan cap untuk hadiah yang tidak pernah ditemukan.
 *
 * Hadiah besar baru bisa keluar mulai release_from (diatur Game Admin; kosong =
 * 14 hari terakhir periode), lalu dibuka bertahap sampai program_end: unit ke-n
 * baru bisa keluar setelah (n-1)/cap jendela itu lewat. Di luar jendela, hadiah
 * besar tidak keluar.
 */
final class BigPrizeQuota
{
    /** Jendela keluar default kalau release_from kosong: 14 hari terakhir periode. */
    public const DEFAULT_RELEASE_DAYS = 14;

    /** Ada sisa kuota untuk katalog ini? Cek biasa, tanpa memotong. */
    public static function available(string $brand, PrizeCatalog $catalog): bool
    {
        $cap = self::cap($brand, $catalog->name);

        return $cap !== null && (int) $cap->used_units < self::unlocked($cap);
    }

    /**
     * Unit yang sudah boleh keluar sampai hari ini. Jendela release_from..program_end
     * dibagi rata per unit: cap 2 untuk jendela 14 hari berarti unit 1 sejak hari
     * pertama jendela, unit 2 sejak hari ke-8.
     */
    public static function unlocked(object $cap, ?\DateTimeInterface $today = null): int
    {
        $units = (int) $cap->cap_units;
        $today = Carbon::instance($today ?? now())->startOfDay();
        $start = self::releaseFrom($cap);
        $end = Carbon::parse($cap->program_end)->startOfDay();

        if ($units <= 0 || $today->lt($start) || $today->gt($end)) {
            return 0;
        }

        $days = (int) $start->diffInDays($end) + 1;
        $elapsed = (int) $start->diffInDays($today);

        $open = 0;
        for ($unit = 0; $unit < $units; $unit++) {
            if (intdiv($unit * $days, $units) <= $elapsed) {
                $open++;
            }
        }

        return $open;
    }

    /** Hari pertama hadiah besar boleh keluar, tidak pernah sebelum program_start. */
    public static function releaseFrom(object $cap): Carbon
    {
        $start = Carbon::parse($cap->program_start)->startOfDay();
        $release = ($cap->release_from ?? null)
            ? Carbon::parse($cap->release_from)->startOfDay()
            : Carbon::parse($cap->program_end)->startOfDay()->subDays(self::DEFAULT_RELEASE_DAYS - 1);

        return $release->max($start);
    }

    /** Potong satu unit secara atomik. False kalau kuota sudah habis atau hadiah tanpa cap. */
    public static function reserve(string $brand, GamePrize $prize): bool
    {
        $name = self::catalogName($prize);
        if ($name === null) {
            return false;
        }

        $cap = self::cap($brand, $name);
        if ($cap === null) {
            return false;
        }

        return DB::table('quota_caps')
            ->where('id', $cap->id)
            ->where('used_units', '<', DB::raw('cap_units'))
            ->where('used_units', '<', self::unlocked($cap))
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

    public static function cap(string $brand, string $name): ?object
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
