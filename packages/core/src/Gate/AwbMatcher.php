<?php

namespace LoyaltyNew\Core\Gate;

final class AwbMatcher
{
    public static function matches(string $awb, string $phone, string $orderAwb, string $orderPhone): bool
    {
        $awb = trim($awb);
        $orderAwb = trim($orderAwb);
        $phone = self::normalizePhone($phone);
        $orderPhone = self::normalizePhone($orderPhone);

        // Fail closed: a blank AWB or phone on either side is never a match.
        if ($awb === '' || $orderAwb === '' || $phone === '' || $orderPhone === '') {
            return false;
        }

        return strcasecmp($awb, $orderAwb) === 0 && $phone === $orderPhone;
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }
}
