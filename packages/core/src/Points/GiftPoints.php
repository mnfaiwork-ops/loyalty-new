<?php

namespace LoyaltyNew\Core\Points;

/**
 * Nilai satu kado poin. Dasarnya nominal produk order setelah diskon:
 * per Rp1.000 dapat `per_thousand` poin, lalu diambil acak antara
 * `min_percent`% sampai 100% dari angka itu, supaya tidak sama persis
 * tapi juga tidak terlalu acak (feedback testing 2026-10-15).
 */
final class GiftPoints
{
    public static function roll(?int $orderAmount, ?string $brand = null): int
    {
        $settings = LoyaltySettings::current($brand);
        $full = self::full($orderAmount, $settings);

        if ($full === null) {
            return $settings['per_gift'];
        }

        $min = max(1, (int) ceil($full * $settings['min_percent'] / 100));

        return random_int(min($min, $full), $full);
    }

    /** Poin 100% untuk nominal ini, atau null kalau nominal tidak ada / terlalu kecil. */
    public static function full(?int $orderAmount, array $settings): ?int
    {
        if ($orderAmount === null || $orderAmount <= 0) {
            return null;
        }

        $full = intdiv($orderAmount * $settings['per_thousand'], 1000);

        return $full > 0 ? $full : null;
    }
}
