<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\PrizeCatalog;

/**
 * Stok bulanan hadiah voucher/merch Gatcha (`prize_catalog.monthly_stock`).
 * Terpakai = kado item itu yang diambil pelanggan bulan ini (klaimnya terbit).
 *
 * Stok dicek saat kado dibuka, bukan dikunci. Dua pelanggan yang membuka item
 * terakhir di detik yang sama bisa sama-sama mengambilnya, jadi stok bisa lebih
 * satu-dua unit. Itu dipilih ketimbang menarik hadiah yang sudah tampil di layar.
 */
final class PrizeStock
{
    public static function usedThisMonth(int $catalogId): int
    {
        return DB::table('claims')
            ->join('game_prizes', 'game_prizes.id', '=', 'claims.game_prize_id')
            ->where('game_prizes.prize_catalog_id', $catalogId)
            ->where('claims.created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public static function remaining(PrizeCatalog $item): ?int
    {
        if ($item->monthly_stock === null) {
            return null;
        }

        return max(0, (int) $item->monthly_stock - self::usedThisMonth((int) $item->id));
    }

    /** Satu item aktif tier ini yang stoknya masih ada, diundi rata. Null kalau tidak ada. */
    public static function pick(string $brand, string $tier): ?int
    {
        $items = PrizeCatalog::where('brand', $brand)
            ->where('tier', $tier)
            ->where('active', true)
            ->get()
            ->filter(fn (PrizeCatalog $item) => self::remaining($item) !== 0);

        return $items->isEmpty() ? null : (int) $items->random()->id;
    }
}
