<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;

final class ClaimService
{
    /** Status yang masih bisa diurus (non-terminal). Yang lewat batas ini di-hangus. */
    public const OPEN_STATUSES = ['menunggu', 'perlu_finance', 'diproses'];

    /** Batas klaim per tier (spec §2): hadiah besar 30 hari, lainnya 14 hari. */
    public static function expiryFor(string $tier): int
    {
        return $tier === 'hadiah_besar' ? 30 : 14;
    }

    /**
     * Hanguskan klaim yang sudah lewat expires_at dan belum selesai (spec §9 cek #6).
     *
     * - Terminal (selesai/hangus) tidak disentuh → idempotent.
     * - Brand-scoped: claims tak punya kolom brand, jadi difilter via relasi sesi.
     *   Kalau $brand null, pakai config('app.brand').
     * - handled_by dibiarkan null: ini tindakan sistem, bukan CS.
     *
     * @return int jumlah claim yang di-hangus
     */
    public static function expireOverdue(?\DateTimeInterface $now = null, ?string $brand = null): int
    {
        $brand = $brand ?: (string) config('app.brand');

        return Claim::query()
            ->where('expires_at', '<', $now ?? now())
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereHas('gameSession', fn ($q) => $q->where('brand', $brand))
            ->update(['status' => 'hangus']);
    }

    public static function create(GameSession $session, GamePrize $prize): Claim
    {
        return Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'claim_code' => self::uniqueCode(),
            'status' => $prize->tier === 'hadiah_besar' ? 'perlu_finance' : 'menunggu',
            'expires_at' => now()->addDays(self::expiryFor($prize->tier)),
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
