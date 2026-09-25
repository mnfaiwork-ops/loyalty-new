<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Verifikasi tanda tangan webhook Markom (HMAC-SHA256 atas body mentah).
 *
 * Header: X-Markom-Signature: <hex digest>
 * Fail-closed: kalau secret kosong atau tanda tangan tak cocok → false.
 */
final class WebhookSignature
{
    public static function verify(string $rawBody, ?string $signature, ?string $secret): bool
    {
        if (! $secret || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, trim($signature));
    }
}
