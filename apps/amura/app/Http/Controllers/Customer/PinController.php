<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberedCustomer;
use App\Support\PendingCustomer;
use App\Support\PhoneInput;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    /** Hash bcrypt pengganti untuk nomor tanpa PIN, supaya waktu respons tidak membocorkan akun. */
    private const DUMMY_HASH = '$2y$12$86x/G0S33lIVpr..ZZ/3Oe0LgespHAsrffAp8.XY9t8uXu4LwKRm2';

    public function show(Request $request)
    {
        $customer = PendingCustomer::customer($request);
        if (! $customer) {
            return redirect()->route('play.form');
        }
        if ($customer->pin_hash === null) {
            return redirect()->route('pin.create');
        }
        if ($locked = self::lockedView($request, $customer->phone, route('pin.show'))) {
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

        $result = self::checkPin($request, $customer->phone, $customer, $pin);
        if ($result === self::LOCKED) {
            return redirect()->route('pin.show');
        }
        if ($result !== null) {
            return back()->withErrors(['pin' => 'PIN salah. Cek lagi angkanya. '.self::leftNote($result)]);
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
     * dikunci dari perangkat/jaringan ini, selain itu sisa percobaan. Salah PIN
     * dihitung per nomor HP, juga untuk nomor yang tidak punya akun, supaya
     * hasilnya tidak membedakan nomor terdaftar dari yang bukan.
     */
    public static function checkPin(Request $request, string $phone, ?Customer $customer, string $pin): string|int|null
    {
        $limiter = AttemptLimiter::fromConfig((string) config('app.brand'), $request->ip());

        return $limiter->serialize($phone, function () use ($limiter, $phone, $customer, $pin) {
            if ($limiter->pinLockedFor($phone) !== null) {
                return self::LOCKED;
            }

            if ($customer && $customer->pin_hash !== null) {
                $matches = CustomerPin::matches($customer, $pin);
            } else {
                // Nomor tanpa PIN tetap menjalankan bcrypt supaya waktunya sama.
                Hash::check($pin, self::DUMMY_HASH);
                $matches = false;
            }

            if (! $matches) {
                return $limiter->recordPinFailure($phone) ? self::LOCKED : $limiter->pinFailsLeft($phone);
            }

            $limiter->clearPin($phone);

            return null;
        });
    }

    /** Sisa percobaan ditampilkan supaya kunci tidak datang tiba-tiba (desain 4d). */
    public static function leftNote(int $left): string
    {
        $minutes = AttemptLimiter::fromConfig((string) config('app.brand'))->lockMinutes();

        return "Sisa {$left} kali sebelum dikunci {$minutes} menit.";
    }

    /** Layar hitung mundur (desain 4f) kalau nomor ini sedang dikunci, atau null. */
    public static function lockedView(Request $request, string $phone, string $retryUrl): ?View
    {
        $limiter = AttemptLimiter::fromConfig((string) config('app.brand'), $request->ip());
        $remaining = $limiter->pinLockedFor($phone);
        if ($remaining === null) {
            return null;
        }

        return view('customer.pin-locked', [
            'maskedPhone' => PhoneInput::mask($phone),
            'remaining' => $remaining,
            'total' => max($limiter->lockMinutes() * 60, $remaining),
            'retryUrl' => $retryUrl,
            'forgotUrl' => WhatsAppLink::forgotPin($phone),
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
        // Identitas pelanggan baru terbukti di sini: id session lama tidak dipakai lagi.
        $request->session()->regenerate();

        return redirect($next)->with('ok', $notice)->withCookie(RememberedCustomer::cookieFor($customer));
    }
}
