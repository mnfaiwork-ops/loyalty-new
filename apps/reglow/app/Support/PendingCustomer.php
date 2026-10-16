<?php

namespace App\Support;

use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Customer;

/**
 * Pelanggan yang identitasnya sudah terbukti (resi + HP, atau PIN sementara)
 * tapi belum lolos layar PIN. Disimpan di session server, bukan cookie.
 *
 * `canSet` true artinya pelanggan boleh menulis PIN baru: belum punya PIN,
 * atau baru saja masuk dengan PIN sementara dari CS. Tanpa tanda ini,
 * pelanggan yang sudah punya PIN hanya boleh memasukkan PIN lamanya.
 */
final class PendingCustomer
{
    private const KEY = 'pin_pending';

    /** @param  string|null  $notice  pesan untuk halaman tujuan setelah PIN beres */
    public static function put(Request $request, Customer $customer, string $next, bool $canSet, ?string $notice = null): void
    {
        $request->session()->put(self::KEY, [
            'id' => $customer->id,
            'next' => $next,
            'can_set' => $canSet,
            'notice' => $notice,
        ]);
    }

    public static function customer(Request $request): ?Customer
    {
        $id = $request->session()->get(self::KEY.'.id');

        return $id ? Customer::find($id) : null;
    }

    public static function next(Request $request): string
    {
        return (string) $request->session()->get(self::KEY.'.next', route('home'));
    }

    public static function notice(Request $request): ?string
    {
        return $request->session()->get(self::KEY.'.notice');
    }

    public static function canSet(Request $request): bool
    {
        return (bool) $request->session()->get(self::KEY.'.can_set', false);
    }

    public static function allowSet(Request $request): void
    {
        $request->session()->put(self::KEY.'.can_set', true);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
