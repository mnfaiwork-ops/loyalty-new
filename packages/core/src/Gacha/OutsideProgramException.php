<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Carbon;

/** Order dibuat di luar periode program brand ini, jadi tidak bisa ikut Gatcha. */
final class OutsideProgramException extends \RuntimeException
{
    public static function before(string $start): self
    {
        return new self('Order ini dibuat sebelum program dimulai ('.self::label($start).'), jadi belum bisa ikut Gatcha.');
    }

    public static function after(string $end): self
    {
        return new self('Order ini dibuat setelah program berakhir ('.self::label($end).'), jadi tidak bisa ikut Gatcha.');
    }

    public static function unknownDate(): self
    {
        return new self('Tanggal order belum terbaca. Coba lagi beberapa saat lagi atau hubungi CS.');
    }

    private static function label(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('j F Y');
    }
}
