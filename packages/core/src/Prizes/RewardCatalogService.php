<?php

namespace LoyaltyNew\Core\Prizes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;

/**
 * Katalog tukar poin bulanan (spec §5). Katalog aktif = katalog terbit terbaru
 * yang bulannya sudah berjalan, jadi katalog lama tetap tampil sampai yang baru terbit.
 */
final class RewardCatalogService
{
    public static function active(string $brand, ?\DateTimeInterface $now = null): ?RewardCatalog
    {
        $period = ($now ? Carbon::instance($now) : now())->format('Y-m');

        return RewardCatalog::withoutGlobalScopes()
            ->where('brand', $brand)
            ->whereNotNull('published_at')
            ->where('period', '<=', $period)
            ->orderByDesc('period')
            ->first();
    }

    public static function exchange(Customer $customer, int $itemId): Claim
    {
        return DB::transaction(function () use ($customer, $itemId) {
            $active = self::active($customer->brand);
            $item = RewardCatalogItem::whereKey($itemId)->lockForUpdate()->first();

            if (! $active || ! $item || (int) $item->reward_catalog_id !== (int) $active->id || ! $item->active) {
                throw new CatalogUnavailableException('Hadiah ini tidak ada di katalog bulan ini.');
            }

            if (! $item->inStock()) {
                throw new CatalogUnavailableException('Stok hadiah ini sudah habis.');
            }

            $settings = LoyaltySettings::current($customer->brand);
            $claim = ClaimService::createFromCatalog($customer, $item);
            (new PointWallet($customer))->spend($claim, $item->points_cost, $settings['min_redeem']);
            $item->increment('stock_used');

            return $claim;
        });
    }

    public static function forPeriod(string $brand, string $period): RewardCatalog
    {
        return RewardCatalog::withoutGlobalScopes()->firstOrCreate(['brand' => $brand, 'period' => $period]);
    }

    /** Salin item dari bulan lain ke draft bulan tujuan. Ditolak kalau tujuan sudah berisi. */
    public static function copyFrom(string $brand, string $fromPeriod, string $toPeriod): int
    {
        return DB::transaction(function () use ($brand, $fromPeriod, $toPeriod) {
            $source = RewardCatalog::withoutGlobalScopes()->where('brand', $brand)->where('period', $fromPeriod)->first();
            if (! $source) {
                throw new CatalogUnavailableException("Katalog {$fromPeriod} tidak ditemukan.");
            }

            $target = self::forPeriod($brand, $toPeriod);
            if ($target->items()->exists()) {
                throw new CatalogUnavailableException("Katalog {$toPeriod} sudah berisi item.");
            }

            $count = 0;
            foreach ($source->items as $item) {
                $target->items()->create([
                    'name' => $item->name,
                    'points_cost' => $item->points_cost,
                    'image_path' => $item->image_path,
                    'stock' => $item->stock,
                    'stock_used' => 0,
                    'active' => $item->active,
                    'sort' => $item->sort,
                ]);
                $count++;
            }

            return $count;
        });
    }

    public static function publish(string $brand, string $period): RewardCatalog
    {
        $catalog = self::forPeriod($brand, $period);
        if (! $catalog->published_at) {
            $catalog->update(['published_at' => now()]);
        }

        return $catalog;
    }

    public static function previousPeriod(string $period): string
    {
        return Carbon::createFromFormat('Y-m-d', $period.'-01')->subMonthNoOverflow()->format('Y-m');
    }
}
