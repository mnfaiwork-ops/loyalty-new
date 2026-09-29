<?php

namespace LoyaltyNew\Core\Points;

use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PointMovement;

/**
 * Dompet poin berbasis lot (spec §4). Satu kado poin = satu lot dengan tanggal
 * hangus sendiri. Tukar memotong lot yang paling cepat hangus lebih dulu, dan
 * pengembalian mengisi lot asal supaya masa berlaku tidak berubah.
 */
final class PointWallet
{
    public function __construct(private Customer $customer)
    {
    }

    public function balance(): int
    {
        return (int) PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->sum('remaining');
    }

    /** @return array{points:int,expires_at:\Carbon\CarbonInterface}|null */
    public function nextExpiring(): ?array
    {
        $lot = PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->where('remaining', '>', 0)
            ->orderBy('expires_at')
            ->first();

        return $lot ? ['points' => $lot->remaining, 'expires_at' => $lot->expires_at] : null;
    }

    /** Poin dari kado Gatcha. Aman dipanggil ulang: satu kado hanya membuat satu lot. */
    public function credit(GamePrize $prize, int $points, int $validityMonths): PointLot
    {
        $existing = PointLot::where('game_prize_id', $prize->id)->first();
        if ($existing) {
            return $existing;
        }

        return $this->newLot($points, $validityMonths, $prize->id);
    }

    /** Saldo tanpa kado (dipakai seeder demo). */
    public function grant(int $points, int $validityMonths): PointLot
    {
        return $this->newLot($points, $validityMonths, null);
    }

    /**
     * Potong poin untuk klaim katalog. Syarat saldo ≥ max(minimal tukar, harga).
     * Dipanggil di dalam transaksi pemanggil supaya klaim ikut batal kalau gagal.
     */
    public function spend(Claim $claim, int $points, int $minimum): void
    {
        $lots = PointLot::where('customer_id', $this->customer->id)
            ->where('expires_at', '>', now())
            ->where('remaining', '>', 0)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $balance = (int) $lots->sum('remaining');
        $required = max($minimum, $points);
        if ($balance < $required) {
            throw new InsufficientPointsException($balance, $required);
        }

        $left = $points;
        foreach ($lots as $lot) {
            if ($left <= 0) {
                break;
            }

            $take = min($lot->remaining, $left);
            $lot->decrement('remaining', $take);
            $this->move($lot, 'spend', -$take, $claim->id);
            $left -= $take;
        }
    }

    /** Kembalikan poin klaim katalog ke lot asalnya. Hanya sekali per klaim. */
    public function refund(Claim $claim): int
    {
        return DB::transaction(function () use ($claim) {
            if (PointMovement::where('claim_id', $claim->id)->where('type', 'refund')->exists()) {
                return 0;
            }

            $total = 0;
            $spends = PointMovement::where('claim_id', $claim->id)->where('type', 'spend')->get();

            foreach ($spends as $spend) {
                $lot = PointLot::whereKey($spend->point_lot_id)->lockForUpdate()->firstOrFail();
                $amount = -$spend->points;

                $this->move($lot, 'refund', $amount, $claim->id);

                // Lot yang sudah lewat masa berlaku tetap hangus: poin yang kembali langsung dicatat hangus.
                if ($lot->expires_at->isPast()) {
                    $this->move($lot, 'expire', -$amount, $claim->id);
                } else {
                    $lot->increment('remaining', $amount);
                }

                $total += $amount;
            }

            return $total;
        });
    }

    /** Kosongkan lot yang lewat masa berlaku. Return jumlah poin yang hangus. */
    public static function expireDue(string $brand, ?\DateTimeInterface $now = null): int
    {
        return DB::transaction(function () use ($brand, $now) {
            $lots = PointLot::where('brand', $brand)
                ->where('expires_at', '<=', $now ?? now())
                ->where('remaining', '>', 0)
                ->lockForUpdate()
                ->get();

            $total = 0;
            foreach ($lots as $lot) {
                PointMovement::create([
                    'brand' => $lot->brand,
                    'customer_id' => $lot->customer_id,
                    'point_lot_id' => $lot->id,
                    'type' => 'expire',
                    'points' => -$lot->remaining,
                ]);
                $total += $lot->remaining;
                $lot->update(['remaining' => 0]);
            }

            return $total;
        });
    }

    private function newLot(int $points, int $validityMonths, ?int $prizeId): PointLot
    {
        return DB::transaction(function () use ($points, $validityMonths, $prizeId) {
            $lot = PointLot::create([
                'brand' => $this->customer->brand,
                'customer_id' => $this->customer->id,
                'game_prize_id' => $prizeId,
                'points' => $points,
                'remaining' => $points,
                'expires_at' => now()->addMonths($validityMonths),
            ]);

            $this->move($lot, 'earn', $points, null);

            return $lot;
        });
    }

    private function move(PointLot $lot, string $type, int $points, ?int $claimId): void
    {
        PointMovement::create([
            'brand' => $lot->brand,
            'customer_id' => $lot->customer_id,
            'point_lot_id' => $lot->id,
            'claim_id' => $claimId,
            'type' => $type,
            'points' => $points,
        ]);
    }
}
