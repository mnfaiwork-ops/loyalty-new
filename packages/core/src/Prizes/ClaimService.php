<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalogItem;

final class ClaimService
{
    /** Dipertahankan untuk kode lama: status yang masih bisa hangus. */
    public const OPEN_STATUSES = ClaimFlow::EXPIRABLE;

    public const CATALOG_EXPIRY_DAYS = 14;

    /** Batas redeem hadiah Gatcha (spec §2): hadiah utama 30 hari, lainnya 14 hari. */
    public static function expiryFor(string $tier): int
    {
        return $tier === 'hadiah_besar' ? 30 : 14;
    }

    /**
     * Hanguskan klaim brand ini yang lewat batas dan belum diproses.
     *
     * @return int jumlah klaim yang di-hangus
     */
    public static function expireOverdue(?\DateTimeInterface $now = null, ?string $brand = null, bool $dryRun = false): int
    {
        $brand = $brand ?: (string) config('app.brand');

        $query = Claim::query()
            ->where('brand', $brand)
            ->where('expires_at', '<', $now ?? now())
            ->whereIn('status', ClaimFlow::EXPIRABLE);

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;
        foreach ($query->pluck('id') as $id) {
            try {
                ClaimFlow::expire(Claim::findOrFail($id));
                $count++;
            } catch (ClaimTransitionException) {
                // Statusnya sudah diubah proses lain sejak query di atas: lewati.
            }
        }

        return $count;
    }

    /** Hadiah utama yang hangus mengembalikan kuotanya. Panggil sekali per transisi ke hangus. */
    public static function releaseBigPrizeQuota(Claim $claim, string $brand): void
    {
        $prize = $claim->gamePrize;

        if ($prize && $prize->tier === 'hadiah_besar') {
            BigPrizeQuota::release($brand, $prize);
        }
    }

    /** Nama hadiah untuk klaim dan layar: nama katalog kalau ada, kalau tidak label tier. */
    public static function prizeName(GamePrize $prize): string
    {
        if ($prize->prize_catalog_id) {
            $name = PrizeCatalog::whereKey($prize->prize_catalog_id)->value('name');
            if ($name) {
                return $name;
            }
        }

        return match ($prize->tier) {
            'hadiah_besar' => 'Hadiah Utama',
            'voucher' => 'Voucher',
            'merch' => 'Merch',
            'produk' => 'Produk',
            default => 'Poin',
        };
    }

    public static function create(GameSession $session, GamePrize $prize): Claim
    {
        $claim = Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'brand' => $session->brand,
            'customer_id' => $session->customer_id,
            'source' => 'gatcha',
            'prize_name' => self::prizeName($prize),
            'claim_code' => self::uniqueCode(),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(self::expiryFor($prize->tier)),
        ]);

        self::firstEvent($claim, 'system');

        return $claim;
    }

    public static function createFromCatalog(Customer $customer, RewardCatalogItem $item): Claim
    {
        $claim = Claim::create([
            'brand' => $customer->brand,
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => $item->name,
            'reward_catalog_item_id' => $item->id,
            'points_spent' => $item->points_cost,
            'claim_code' => self::uniqueCode(),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(self::CATALOG_EXPIRY_DAYS),
        ]);

        self::firstEvent($claim, 'customer');

        return $claim;
    }

    private static function firstEvent(Claim $claim, string $actor): void
    {
        ClaimEvent::create([
            'claim_id' => $claim->id,
            'from_status' => null,
            'to_status' => 'belum_redeem',
            'actor' => $actor,
        ]);
    }

    private static function uniqueCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
        } while (Claim::where('claim_code', $code)->exists());

        return $code;
    }
}
