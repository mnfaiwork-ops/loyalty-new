<?php

namespace LoyaltyNew\Core\Seeding;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

/**
 * Isi katalog tukar poin bulan pertama per brand (keputusan Fai 2026-10-15).
 * Harga poin dari workbook final 2026-09-23 sheet "Katalog & Peluang Hadiah".
 * Item `active=false` sudah tersimpan tapi tidak tampil, supaya Game Admin
 * tinggal mencentang saat rotasi bulan berikutnya.
 */
final class LaunchCatalog
{
    /** Minimal saldo untuk menukar, sama dengan item termurah (keputusan Fai 2026-10-15). */
    public const MIN_REDEEM = 800;

    /**
     * Harga poin: biaya hadiah sekitar 0,5-1,25% dari belanja (keputusan Fai 2026-10-15).
     * Poin maksimal per order = nilai belanja / Rp1.000, jadi order Rp250rb paling banyak 250 poin.
     *
     * @return list<array{name:string,points_cost:int,stock:?int,active:bool,sort:int}>
     */
    public static function items(string $brand): array
    {
        $shared = [
            ['name' => 'Voucher Gratis Ongkir maks Rp8.000', 'points_cost' => 800, 'stock' => 200, 'active' => true, 'sort' => 1],
            ['name' => 'Pouch Exclusive', 'points_cost' => 1000, 'stock' => 100, 'active' => true, 'sort' => 2],
            ['name' => 'Exclusive ToteBag', 'points_cost' => 1200, 'stock' => 100, 'active' => false, 'sort' => 3],
            ['name' => 'Voucher Diskon 25rb', 'points_cost' => 2000, 'stock' => 100, 'active' => true, 'sort' => 4],
            ['name' => 'Voucher Diskon 50rb', 'points_cost' => 4000, 'stock' => 50, 'active' => true, 'sort' => 8],
            ['name' => 'Voucher Diskon 100rb', 'points_cost' => 8000, 'stock' => 25, 'active' => false, 'sort' => 9],
        ];

        $products = match ($brand) {
            'amura' => [
                ['name' => 'Darkspot Serum', 'points_cost' => 2500, 'stock' => 50, 'active' => true, 'sort' => 5],
                ['name' => 'Gold Serum', 'points_cost' => 3000, 'stock' => 50, 'active' => false, 'sort' => 6],
            ],
            'reglow' => [
                ['name' => 'Night Cream', 'points_cost' => 2500, 'stock' => 50, 'active' => true, 'sort' => 5],
                ['name' => 'Sunscreen Glow', 'points_cost' => 3000, 'stock' => 50, 'active' => false, 'sort' => 6],
                // HPP belum diisi di workbook: jangan diaktifkan sebelum HPP ada.
                ['name' => 'Rice PDRN Serum', 'points_cost' => 3000, 'stock' => 50, 'active' => false, 'sort' => 7],
            ],
            default => throw new \InvalidArgumentException("Brand tidak dikenal: {$brand}"),
        };

        return array_merge($shared, $products);
    }

    /**
     * Pasang isi katalog ke bulan itu lalu terbitkan, dan set minimal tukar.
     * Item dicocokkan per nama, jadi aman dijalankan ulang. Item lain di bulan itu
     * dinonaktifkan, tidak dihapus, karena bisa saja sudah pernah ditukar.
     *
     * @return array{active:int,hidden:int}
     */
    public static function apply(string $brand, string $period): array
    {
        return DB::transaction(function () use ($brand, $period) {
            $catalog = RewardCatalogService::forPeriod($brand, $period);
            $items = self::items($brand);

            foreach ($items as $item) {
                $catalog->items()->updateOrCreate(['name' => $item['name']], [
                    'points_cost' => $item['points_cost'],
                    'stock' => $item['stock'],
                    'active' => $item['active'],
                    'sort' => $item['sort'],
                ]);
            }

            $catalog->items()->whereNotIn('name', array_column($items, 'name'))->update(['active' => false]);
            RewardCatalogService::publish($brand, $period);
            LoyaltySettings::store(['min_redeem' => self::MIN_REDEEM] + LoyaltySettings::current($brand), $brand);

            $active = count(array_filter($items, fn ($item) => $item['active']));

            return ['active' => $active, 'hidden' => $catalog->items()->count() - $active];
        });
    }
}
