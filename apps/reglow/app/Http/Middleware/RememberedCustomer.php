<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Customer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pelanggan yang sudah masuk dengan resi + HP diingat di perangkat ini 30 hari
 * (spec poin-redeem §7). Cookie dienkripsi Laravel dan hanya berlaku untuk brand ini.
 */
final class RememberedCustomer
{
    public const COOKIE = 'loyalty_customer';

    public function handle(Request $request, Closure $next): Response
    {
        $customer = self::resolve($request);

        if (! $customer) {
            $redirect = redirect()->route('play.form')->withoutCookie(self::COOKIE);

            return $request->hasCookie(self::COOKIE)
                ? $redirect->withErrors(['awb' => 'Masuk lagi dengan resi dan nomor HP.'])
                : $redirect;
        }

        $request->attributes->set('customer', $customer);

        return $next($request);
    }

    public static function resolve(Request $request): ?Customer
    {
        $raw = $request->cookie(self::COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data) || ! isset($data['id']) || ($data['brand'] ?? null) !== config('app.brand')) {
            return null;
        }

        // BrandScope memastikan pelanggan brand lain tidak ditemukan.
        return Customer::find((int) $data['id']);
    }

    public static function cookieFor(Customer $customer): Cookie
    {
        $minutes = 60 * 24 * (int) config('play.remember_days', 30);

        return cookie(self::COOKIE, json_encode(['id' => $customer->id, 'brand' => $customer->brand]), $minutes);
    }
}
