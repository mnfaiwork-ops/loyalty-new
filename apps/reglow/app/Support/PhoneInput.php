<?php

namespace App\Support;

/** Nomor HP dari form pelanggan. */
final class PhoneInput
{
    /** Form menampilkan "+62" di depan kolom HP, jadi "812..." berarti "0812...". */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits !== '' && ! str_starts_with($digits, '0') && ! str_starts_with($digits, '62')) {
            return '0'.$digits;
        }

        return $phone;
    }

    /** Nomor HP disamarkan: 4 digit awal + •••• + 4 digit akhir (sama dengan Customer::maskedPhone). */
    public static function mask(string $phone): string
    {
        return strlen($phone) <= 8 ? $phone : substr($phone, 0, 4).'••••'.substr($phone, -4);
    }

    /** Nomor HP dikelompokkan 4 angka supaya mudah dicocokkan dengan profil chat: 0812 3456 7890. */
    public static function display(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return $digits === '' ? $phone : trim(chunk_split($digits, 4, ' '));
    }
}
