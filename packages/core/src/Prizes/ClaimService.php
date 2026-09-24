<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;

final class ClaimService
{
    /** Batas klaim per tier (spec §2): hadiah besar 30 hari, lainnya 14 hari. */
    public static function expiryFor(string $tier): int
    {
        return $tier === 'hadiah_besar' ? 30 : 14;
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
