<?php

namespace LoyaltyNew\Core\Gacha;

final class Grid
{
    public const SLOTS = 24;
    public const PICKS = 3;

    /** @return int[] nomor slot 1..24 */
    public static function slots(): array
    {
        return range(1, self::SLOTS);
    }
}
