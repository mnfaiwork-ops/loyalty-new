<?php

namespace LoyaltyNew\Core\Prizes;

final class TierConfig
{
    /** Bobot tier default (spec §5): poin 65 / voucher 32 / merch 3. */
    public static function default(): array
    {
        return ['poin' => 65, 'voucher' => 32, 'merch' => 3];
    }
}
