<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;

/** Kado yang diambil: poin langsung masuk saldo, lainnya menjadi klaim belum_redeem. */
final class PrizeAwarder
{
    /** @return array{type:'points',points:int,balance:int}|array{type:'claim',claim:Claim} */
    public static function award(GameSession $session, GamePrize $prize): array
    {
        if ($prize->tier === 'poin') {
            $settings = LoyaltySettings::current($session->brand);
            $customer = Customer::withoutGlobalScopes()->findOrFail($session->customer_id);
            $wallet = new PointWallet($customer);
            $lot = $wallet->credit($prize, $settings['per_gift'], $settings['validity_months']);

            return ['type' => 'points', 'points' => $lot->points, 'balance' => $wallet->balance()];
        }

        $claim = Claim::where('game_prize_id', $prize->id)->first() ?? ClaimService::create($session, $prize);

        return ['type' => 'claim', 'claim' => $claim];
    }
}
