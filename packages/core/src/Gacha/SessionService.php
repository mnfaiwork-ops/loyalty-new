<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\QuotaGuard;
use LoyaltyNew\Core\Prizes\TierConfig;

final class SessionService
{
    public function __construct(
        private OrderGate $gate,
        private string $brand,
    ) {
    }

    /** Buka (atau pakai ulang) sesi untuk AWB+HP. Melempar kalau gate menolak. */
    public function open(string $awb, string $phone): GameSession
    {
        $result = $this->gate->isDelivered($awb, $phone);
        if (! $result->delivered) {
            throw new \RuntimeException($result->reason ?? 'Paket belum diterima');
        }

        $customer = Customer::firstOrCreate(
            ['phone' => AwbMatcher::normalizePhone($phone), 'brand' => $this->brand],
        );
        $customer->update(['last_seen_at' => now()]);

        $existing = GameSession::where('customer_id', $customer->id)
            ->where('awb', $awb)
            ->where('expires_at', '>', now())
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($customer, $awb) {
            $session = GameSession::create([
                'customer_id' => $customer->id,
                'brand' => $this->brand,
                'session_token' => bin2hex(random_bytes(32)),
                'chance_total' => 2,
                'chance_used' => 0,
                'tier_config' => TierConfig::default(),
                'awb' => $awb,
                'gate_source' => 'stub',
                'expires_at' => now()->addDays(30),
            ]);

            $engine = new DrawEngine($session->tier_config);
            foreach (Grid::slots() as $slot) {
                GamePrize::create([
                    'game_session_id' => $session->id,
                    'slot' => $slot,
                    'tier' => $engine->pick(random_int(1, max(1, array_sum($session->tier_config)))),
                ]);
            }

            $this->reserveBigPrize($session);

            return $session;
        });
    }

    /** Kunci chance di DB. Return false kalau sudah habis. */
    public function consumeChance(GameSession $session): bool
    {
        $affected = GameSession::where('id', $session->id)
            ->whereColumn('chance_used', '<', 'chance_total')
            ->increment('chance_used');

        return $affected > 0;
    }

    /**
     * Ambil satu hadiah besar dari kuota kalau budget masih ada.
     * Race-safe: klaim kuota via increment atomik, bukan cek-lalu-tulis.
     */
    private function reserveBigPrize(GameSession $session): ?GamePrize
    {
        $catalogs = PrizeCatalog::where('brand', $this->brand)
            ->where('tier', 'hadiah_besar')
            ->where('is_capped', true)
            ->where('active', true)
            ->get();

        foreach ($catalogs as $catalog) {
            $cap = DB::table('quota_caps')
                ->where('brand', $this->brand)
                ->where('prize_name', $catalog->name)
                ->first();

            if (! $cap || ! QuotaGuard::canAward((int) $cap->cap_units, (int) $cap->used_units)) {
                continue;
            }

            $won = DB::table('quota_caps')
                ->where('id', $cap->id)
                ->where('used_units', '<', DB::raw('cap_units'))
                ->increment('used_units');

            if ($won < 1) {
                continue;
            }

            $slot = $session->prizes()
                ->where('tier', '!=', 'hadiah_besar')
                ->inRandomOrder()
                ->first();

            if (! $slot) {
                DB::table('quota_caps')->where('id', $cap->id)->decrement('used_units');
                continue;
            }

            $slot->update([
                'tier' => 'hadiah_besar',
                'prize_catalog_id' => $catalog->id,
            ]);

            return $slot->fresh();
        }

        return null;
    }
}
