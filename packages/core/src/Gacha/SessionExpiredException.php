<?php

namespace LoyaltyNew\Core\Gacha;

/** Masa main sesi untuk paket ini sudah habis. Bukan salah input, jadi tidak dihitung ke lockout. */
final class SessionExpiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Masa main untuk paket ini sudah berakhir.');
    }
}
