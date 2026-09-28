<?php

namespace LoyaltyNew\Core\Gate;

final class AwbMatcher
{
    public static function matches(string $awb, string $phone, string $orderAwb, string $orderPhone): bool
    {
        $awb = self::normalizeAwb($awb);
        $orderAwb = self::normalizeAwb($orderAwb);
        $phone = self::normalizePhone($phone);
        $orderPhone = self::normalizePhone($orderPhone);

        // Fail closed: a blank AWB or phone on either side is never a match.
        if ($awb === '' || $orderAwb === '' || $phone === '' || $orderPhone === '') {
            return false;
        }

        return $awb === $orderAwb && $phone === $orderPhone;
    }

    /**
     * Bentuk baku AWB: tanpa spasi tepi, huruf besar. Dipakai di semua tempat
     * AWB disimpan atau dicari, supaya "jx123" dan "JX123" dihitung satu paket.
     */
    public static function normalizeAwb(string $awb): string
    {
        return strtoupper(trim($awb));
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
