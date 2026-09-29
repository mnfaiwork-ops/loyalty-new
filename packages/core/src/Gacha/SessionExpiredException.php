<?php

namespace LoyaltyNew\Core\Gacha;

/** Masa main sesi untuk paket ini sudah habis. Bukan salah input, jadi tidak dihitung ke lockout. */
final class SessionExpiredException extends \RuntimeException
{
    /** @param  int|null  $customerId  pemilik sesi, supaya app bisa tetap membuka area pelanggan */
    public function __construct(public readonly ?int $customerId = null)
    {
        parent::__construct('Masa main untuk paket ini sudah berakhir.');
    }
}
