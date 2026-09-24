<?php

namespace LoyaltyNew\Core\Gate;

final class AwbMatcher
{
    public static function matches(string $awb, string $phone, string $orderAwb, string $orderPhone): bool
    {
        if (strcasecmp(trim($awb), trim($orderAwb)) !== 0) {
            return false;
        }
        return self::normalizePhone($phone) === self::normalizePhone($orderPhone);
    }

    private static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}
