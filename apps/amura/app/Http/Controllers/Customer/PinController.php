<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberedCustomer;
use App\Support\PendingCustomer;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Models\Customer;

/**
 * Layar PIN setelah resi + HP cocok, atau setelah masuk dengan PIN sementara
 * dari CS (spec PIN 2026-10-07).
 */
final class PinController extends Controller
{
    /** Penanda dari checkPin(): nomor ini sedang dikunci karena terlalu banyak PIN salah. */
    public const LOCKED = 'locked';

    public function show(Request $request)
    {
        $customer = PendingCustomer::customer($request);
        if (! $customer) {
            return redirect()->route('play.form');
        }
        if ($customer->pin_hash === null) {
            return redirect()->route('pin.create');
        }
        if ($locked = self::lockedView($customer, route('pin.show'))) {
            return $locked;
        }

        return view('customer.pin', [
            'customer' => $customer,
            'forgotUrl' => WhatsAppLink::forgotPin($customer->phone),
        ]);
    }

    public function verify(Request $request)
    {
        $customer = PendingCustomer::customer($request);
        if (! $customer) {
            return redirect()->route('play.form');
        }

        $pin = (string) $request->validate(
            ['pin' => ['required', 'string', 'max:8']],
            ['pin.required' => 'Masukkan PIN-mu.'],
        )['pin'];

        if ($error = self::checkPin($customer, $pin)) {
            return $error === self::LOCKED
                ? redirect()->route('pin.show')
                : back()->withErrors(['pin' => $error]);
        }

        return self::admit($request, $customer, PendingCustomer::next($request), PendingCustomer::notice($request));
    }

    public function create(Request $request)
    {
        $customer = PendingCustomer::customer($request);
        if (! $customer) {
            return redirect()->route('play.form');
        }
        if (! PendingCustomer::canSet($request)) {
            return redirect()->route('pin.show');
        }

        return view('customer.pin-create', [
            'customer' => $customer,
            'replacing' => $customer->pin_must_change,
        ]);
    }

    public function store(Request $request)
    {
        $customer = PendingCustomer::customer($request);
        if (! $customer) {
            return redirect()->route('play.form');
        }
        if (! PendingCustomer::canSet($request)) {
            return redirect()->route('pin.show');
        }

        $data = $request->validate([
            'pin' => ['required', 'string', 'max:8'],
            'pin_confirmation' => ['required', 'string', 'max:8'],
        ], [
            'pin.required' => 'Buat PIN '.CustomerPin::LENGTH.' angka dulu.',
            'pin_confirmation.required' => 'Ketik PIN yang sama sekali lagi.',
        ]);

        if ($problem = CustomerPin::problem($data['pin'])) {
            return back()->withErrors(['pin' => $problem]);
        }
        if (! hash_equals($data['pin'], $data['pin_confirmation'])) {
            return back()->withErrors(['pin_confirmation' => 'PIN kedua tidak sama dengan yang pertama.']);
        }

        CustomerPin::set($customer, $data['pin']);

        return self::admit($request, $customer->fresh(), PendingCustomer::next($request),
            PendingCustomer::notice($request) ?? 'PIN tersimpan. Lain kali masuk cukup dengan nomor HP dan PIN ini.');
    }

    /**
     * Null kalau PIN cocok, self::LOCKED kalau nomor ini sedang atau baru saja
     * dikunci, selain itu pesan galat. Salah PIN dihitung per nomor HP.
     */
    public static function checkPin(Customer $customer, string $pin): ?string
    {
        $limiter = AttemptLimiter::fromConfig((string) config('app.brand'));

        if ($limiter->pinLockedFor($customer->phone) !== null) {
            return self::LOCKED;
        }

        if (! CustomerPin::matches($customer, $pin)) {
            if ($limiter->recordPinFailure($customer->phone)) {
                return self::LOCKED;
            }

            // Sisa percobaan ditampilkan supaya kunci tidak datang tiba-tiba (desain 4d).
            return 'PIN salah. Cek lagi angkanya. Sisa '.$limiter->pinFailsLeft($customer->phone)
                .' kali sebelum dikunci '.$limiter->lockMinutes().' menit.';
        }

        $limiter->clearPin($customer->phone);

        return null;
    }

    /** Layar hitung mundur (desain 4f) kalau nomor ini sedang dikunci, atau null. */
    public static function lockedView(Customer $customer, string $retryUrl): ?View
    {
        $limiter = AttemptLimiter::fromConfig((string) config('app.brand'));
        $remaining = $limiter->pinLockedFor($customer->phone);
        if ($remaining === null) {
            return null;
        }

        return view('customer.pin-locked', [
            'customer' => $customer,
            'remaining' => $remaining,
            'total' => $limiter->lockMinutes() * 60,
            'retryUrl' => $retryUrl,
            'forgotUrl' => WhatsAppLink::forgotPin($customer->phone),
        ]);
    }

    /** PIN cocok. PIN sementara dari CS harus diganti dulu, selain itu perangkat diingat dan lanjut. */
    public static function admit(Request $request, Customer $customer, string $next, ?string $notice = null): RedirectResponse
    {
        if ($customer->pin_must_change) {
            PendingCustomer::put($request, $customer, $next, canSet: true, notice: $notice);

            return redirect()->route('pin.create');
        }

        PendingCustomer::forget($request);

        return redirect($next)->with('ok', $notice)->withCookie(RememberedCustomer::cookieFor($customer));
    }
}
