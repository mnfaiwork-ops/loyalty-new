<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberedCustomer;
use App\Support\PhoneInput;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Models\Customer;

/** Masuk lagi dengan nomor HP + PIN, tanpa resi (spec PIN 2026-10-07). */
final class SignInController extends Controller
{
    /** Session: pelanggan yang nomornya dikunci saat masuk di sini, untuk layar hitung mundur. */
    private const LOCKED = 'sign_in_locked';

    public function show(Request $request)
    {
        if (RememberedCustomer::resolve($request)) {
            return redirect()->route('home');
        }

        $lockedId = $request->session()->get(self::LOCKED);
        $locked = $lockedId ? Customer::find($lockedId) : null;
        if ($locked && $view = PinController::lockedView($locked, route('customer.login'))) {
            return $view;
        }
        $request->session()->forget(self::LOCKED);

        return view('customer.sign-in', ['forgotUrl' => WhatsAppLink::forgotPin(null)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'pin' => ['required', 'string', 'max:8'],
        ], [
            'phone.required' => 'Nomor HP wajib diisi.',
            'pin.required' => 'Masukkan PIN-mu.',
            'max' => 'Isian terlalu panjang. Cek lagi angkanya.',
        ]);

        $phone = AwbMatcher::normalizePhone(PhoneInput::normalize($data['phone']));
        $customer = Customer::where('phone', $phone)->first();

        if (! $customer || $customer->pin_hash === null) {
            return back()->withInput($request->only('phone'))->withErrors([
                'phone' => 'Nomor ini belum punya PIN. Masuk dengan nomor resi dulu, lalu buat PIN.',
            ]);
        }

        if ($error = PinController::checkPin($customer, $data['pin'])) {
            if ($error === PinController::LOCKED) {
                $request->session()->put(self::LOCKED, $customer->id);

                return redirect()->route('customer.login');
            }

            return back()->withInput($request->only('phone'))->withErrors(['pin' => $error]);
        }

        $customer->update(['last_seen_at' => now()]);

        return PinController::admit($request, $customer, route('home'));
    }
}
