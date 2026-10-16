<?php

namespace App\Http\Middleware;

use App\Support\PendingCustomer;
use Closure;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Customer;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pelanggan yang sudah lolos PIN diingat di perangkat ini 30 hari
 * (spec poin-redeem §7, spec PIN 2026-10-07). Cookie dienkripsi Laravel,
 * hanya berlaku untuk brand ini, dan membawa versi PIN dan versi perangkat:
 * begitu PIN diganti atau direset CS, atau pelanggan keluar, semua perangkat
 * yang diingat sebelumnya (termasuk cookie yang tersalin) harus masuk lagi.
 */
final class RememberedCustomer
{
    public const COOKIE = 'loyalty_customer';

    public function handle(Request $request, Closure $next): Response
    {
        $customer = self::resolve($request);

        if ($customer) {
            $request->attributes->set('customer', $customer);

            return $next($request);
        }

        // Cookie dari sebelum ada PIN: pelanggan sudah terbukti lewat resi + HP,
        // jadi cukup diminta membuat PIN sekali.
        $known = self::decode($request);
        if ($known && $known->pin_hash === null) {
            PendingCustomer::put($request, $known, $request->fullUrl(), canSet: true);

            return redirect()->route('pin.create');
        }

        if (! $request->hasCookie(self::COOKIE)) {
            return redirect()->route('play.form');
        }

        return redirect()->route('customer.login')
            ->withoutCookie(self::COOKIE)
            ->withErrors(['phone' => 'Masuk lagi dengan nomor HP dan PIN.']);
    }

    /** Pelanggan dari cookie, hanya kalau PIN-nya masih sama dengan saat perangkat diingat. */
    public static function resolve(Request $request): ?Customer
    {
        $customer = self::decode($request);
        if (! $customer || $customer->pin_hash === null || $customer->pin_must_change) {
            return null;
        }

        $data = json_decode((string) $request->cookie(self::COOKIE), true);

        $current = ($data['pin'] ?? null) === $customer->pin_version
            && (int) ($data['dev'] ?? 0) === (int) $customer->device_version;

        return $current ? $customer : null;
    }

    /** Keluar: cabut semua perangkat yang diingat untuk pelanggan di cookie ini. */
    public static function forgetDevices(Request $request): void
    {
        $customer = self::resolve($request);
        if ($customer) {
            $customer->increment('device_version');
        }
    }

    public static function cookieFor(Customer $customer): Cookie
    {
        $minutes = 60 * 24 * (int) config('play.remember_days', 30);

        return cookie(self::COOKIE, self::payload($customer), $minutes);
    }

    public static function payload(Customer $customer): string
    {
        return json_encode([
            'id' => $customer->id,
            'brand' => $customer->brand,
            'pin' => $customer->pin_version,
            'dev' => (int) $customer->device_version,
        ]);
    }

    private static function decode(Request $request): ?Customer
    {
        $raw = $request->cookie(self::COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data) || ! isset($data['id']) || ($data['brand'] ?? null) !== config('app.brand')) {
            return null;
        }

        // BrandScope memastikan pelanggan brand lain tidak ditemukan.
        return Customer::find((int) $data['id']);
    }
}
