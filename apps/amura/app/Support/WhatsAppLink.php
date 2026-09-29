<?php

namespace App\Support;

use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;

/** Link wa.me ke CS resmi dengan pesan redeem yang sudah terisi (desain 2d). */
final class WhatsAppLink
{
    /** Nomor CS dalam format 62xxxx, atau null kalau belum diatur. */
    public static function number(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('loyalty.cs_whatsapp'));
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function message(Claim $claim, Customer $customer): string
    {
        $brand = config('play.themes.'.config('app.brand').'.name', 'Amura');

        return implode("\n", [
            "Halo CS {$brand}, saya mau redeem hadiah.",
            "Hadiah: {$claim->prize_name}",
            "Kode: {$claim->claim_code}",
            'Nama: '.($customer->name ?: '-'),
            "Nomor pemenang: {$customer->phone}",
        ]);
    }

    public static function url(Claim $claim, Customer $customer): ?string
    {
        $number = self::number();

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode(self::message($claim, $customer)) : null;
    }
}
