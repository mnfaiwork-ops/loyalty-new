<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Points\GiftPoints;
use LoyaltyNew\Core\Prizes\BigPrizeQuota;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Prizes\PrizeStock;
use LoyaltyNew\Core\Prizes\TierConfig;

final class SessionService
{
    public function __construct(
        private OrderGate $gate,
        private string $brand,
    ) {
    }

    /**
     * Buka (atau pakai ulang) sesi untuk AWB+HP. Melempar kalau gate menolak,
     * atau SessionExpiredException kalau masa main paket ini sudah habis.
     */
    public function open(string $awb, string $phone): GameSession
    {
        $awb = AwbMatcher::normalizeAwb($awb);

        $result = $this->gate->isDelivered($awb, $phone);
        if (! $result->delivered) {
            throw new \RuntimeException($result->reason ?? 'Paket belum diterima');
        }

        $customer = Customer::firstOrCreate(
            ['phone' => AwbMatcher::normalizePhone($phone), 'brand' => $this->brand],
        );
        // Nama dari data order (spec poin-redeem §2 #8). Gate stub tidak memberi nama.
        $customer->update(array_filter(['last_seen_at' => now(), 'name' => $result->customerName]));

        // Satu paket = satu sesi seumur hidup (unique customer_id+awb). Sesi yang
        // sudah lewat masa main tidak dipakai ulang dan tidak diganti yang baru.
        $existing = GameSession::where('customer_id', $customer->id)
            ->where('awb', $awb)
            ->first();
        if ($existing) {
            return self::ensureActive($existing);
        }

        try {
            return $this->createSession($customer, $awb, $result->orderAmount);
        } catch (\Illuminate\Database\QueryException $e) {
            // Lost the race to a concurrent open(): the unique (customer_id, awb)
            // index rejected our insert, so return the winner's session.
            $raced = GameSession::where('customer_id', $customer->id)
                ->where('awb', $awb)
                ->first();
            if ($raced) {
                return self::ensureActive($raced);
            }
            throw $e;
        }
    }

    public static function isExpired(GameSession $session): bool
    {
        return $session->expires_at !== null && $session->expires_at->isPast();
    }

    private static function ensureActive(GameSession $session): GameSession
    {
        if (self::isExpired($session)) {
            throw new SessionExpiredException((int) $session->customer_id);
        }

        return $session;
    }

    private function createSession(Customer $customer, string $awb, ?int $orderAmount): GameSession
    {
        return DB::transaction(function () use ($customer, $awb, $orderAmount) {
            $session = GameSession::create([
                'customer_id' => $customer->id,
                'brand' => $this->brand,
                'session_token' => bin2hex(random_bytes(32)),
                'chance_total' => 2,
                'chance_used' => 0,
                'tier_config' => TierConfig::current(),
                'awb' => $awb,
                'gate_source' => (string) config('markom.gate', 'stub'),
                'expires_at' => now()->addDays(30),
                'order_amount' => $orderAmount,
            ]);

            $this->drawRound($session, 1);

            return $session;
        });
    }

    /**
     * Siapkan papan kesempatan yang sedang dimainkan kalau belum ada. Setiap
     * kesempatan mendapat 24 kado yang diundi baru, jadi kesempatan kedua
     * kembali penuh. Pemanggil harus sudah mengunci sesi.
     */
    public function ensureRound(GameSession $session): void
    {
        if ($session->chance_used >= $session->chance_total || $session->boardPrizes()->exists()) {
            return;
        }

        $this->drawRound($session, $session->currentRound());
    }

    private function drawRound(GameSession $session, int $round): void
    {
        $paused = GameControls::paused($this->brand);
        $weights = $paused ? ['poin' => 1] : $this->weights($session);

        $engine = new DrawEngine($weights);
        foreach (Grid::slots() as $slot) {
            GamePrize::create([
                'game_session_id' => $session->id,
                'round' => $round,
                'slot' => $slot,
                'tier' => $engine->pick(random_int(1, array_sum($weights))),
            ]);
        }

        // Satu sesi paling banyak satu hadiah utama: yang sudah menang di
        // kesempatan sebelumnya tidak diberi lagi di papan berikutnya.
        $alreadyWon = $session->prizes()->where('tier', 'hadiah_besar')->where('taken', true)->exists();
        if (! $paused && ! $alreadyWon && GameControls::rollBigPrize($this->brand)) {
            $this->placeBigPrize($session, $round);
        }
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
     * Buka ronde (3 kado pilihan) di dalam transaksi pemanggil yang sudah
     * mengunci sesi. Hadiah besar yang baru terbuka memotong kuota di sini;
     * kalau kuota sudah diambil sesi lain, kado itu diundi ulang ke tier biasa
     * SEBELUM tampil, jadi pelanggan tidak pernah melihat hadiah besar yang
     * lalu dibatalkan. Hal yang sama untuk voucher/merch yang stok bulanannya
     * habis, dan untuk semua kado non-poin saat Gatcha dijeda: kado jadi poin.
     *
     * @param  Collection<int, GamePrize>  $round
     * @return Collection<int, GamePrize> ronde dengan tier terbaru
     */
    public function openRound(GameSession $session, Collection $round): Collection
    {
        $paused = GameControls::paused($this->brand);

        foreach ($round as $prize) {
            if ($prize->opened) {
                continue;
            }

            if ($paused && $prize->tier !== 'poin') {
                $prize->tier = 'poin';
                $prize->prize_catalog_id = null;
            }

            if ($prize->tier === 'hadiah_besar' && ! BigPrizeQuota::reserve($this->brand, $prize)) {
                $prize->tier = $this->drawRegularTier($session);
                $prize->prize_catalog_id = null;
            }

            // Voucher/Merch mendapat item spesifik supaya CS tahu apa yang dikirim.
            if (in_array($prize->tier, ['voucher', 'merch'], true) && ! $prize->prize_catalog_id) {
                $prize->prize_catalog_id = PrizeStock::pick($this->brand, $prize->tier);
                if ($prize->prize_catalog_id === null) {
                    $prize->tier = 'poin';
                }
            }

            if ($prize->tier === 'poin' && $prize->points === null) {
                $prize->points = GiftPoints::roll($session->order_amount, $this->brand);
            }

            $prize->opened = true;
            $prize->save();
        }

        return $round;
    }

    private function drawRegularTier(GameSession $session): string
    {
        $weights = $this->weights($session);

        return (new DrawEngine($weights))->pick(random_int(1, array_sum($weights)));
    }

    private function weights(GameSession $session): array
    {
        $weights = $session->tier_config ?: TierConfig::default();

        return array_sum($weights) > 0 ? $weights : TierConfig::default();
    }

    /**
     * Taruh satu hadiah besar di slot acak kalau katalog masih punya kuota.
     * Kuota BELUM dipotong di sini (lihat openRound).
     */
    private function placeBigPrize(GameSession $session, int $round): ?GamePrize
    {
        $catalogs = PrizeCatalog::where('brand', $this->brand)
            ->where('tier', 'hadiah_besar')
            ->where('is_capped', true)
            ->where('active', true)
            ->get();

        foreach ($catalogs as $catalog) {
            if (! BigPrizeQuota::available($this->brand, $catalog)) {
                continue;
            }

            $slot = $session->prizes()->where('round', $round)->inRandomOrder()->first();
            $slot->update([
                'tier' => 'hadiah_besar',
                'prize_catalog_id' => $catalog->id,
            ]);

            return $slot->fresh();
        }

        return null;
    }
}
