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

    /** Pelanggan minta reset PIN ke CS. CS mencocokkan nomor pengirim dengan nomor akun. */
    public static function forgotPin(?string $phone): ?string
    {
        $number = self::number();
        if (! $number) {
            return null;
        }

        $text = "Halo CS ".self::brandName().", saya lupa PIN Poin & Hadiah.\nNomor HP akun: ".($phone ?: '');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    /** CS mengirim PIN sementara ke nomor pelanggan sendiri. */
    public static function temporaryPin(Customer $customer, string $pin): string
    {
        return 'https://wa.me/'.self::international($customer->phone).'?text='.rawurlencode(self::temporaryPinMessage($customer, $pin));
    }

    /** Isi pesan PIN sementara (desain 3l), juga ditampilkan ke CS sebelum dikirim. */
    public static function temporaryPinMessage(Customer $customer, string $pin): string
    {
        $brand = self::brandName();
        $name = trim(strtok((string) $customer->name, ' ') ?: '');
        $host = parse_url(route('customer.login'), PHP_URL_HOST) ?: url('/');

        return ($name !== '' ? "Halo {$name}, ini" : 'Halo, ini')." PIN sementara akun {$brand} kamu: {$pin}. "
            ."Pakai sekali untuk masuk di {$host}, lalu buat PIN baru. CS {$brand} tidak akan pernah menanyakan PIN-mu.";
    }

    private static function brandName(): string
    {
        return config('play.themes.'.config('app.brand').'.name', 'Amura');
    }

    public static function international(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
