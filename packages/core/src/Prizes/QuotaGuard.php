<?php

namespace LoyaltyNew\Core\Prizes;

final class QuotaGuard
{
    public static function canAward(int $capUnits, int $usedUnits): bool
    {
        return $usedUnits < $capUnits;
    }

    public static function remaining(int $capUnits, int $usedUnits): int
    {
        return max(0, $capUnits - $usedUnits);
    }
}
