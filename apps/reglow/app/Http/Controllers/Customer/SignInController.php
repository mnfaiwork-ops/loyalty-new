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
    /** Session: nomor HP yang dikunci saat masuk di sini, untuk layar hitung mundur. */
    private const LOCKED = 'sign_in_locked';

    /**
     * Satu pesan untuk nomor tanpa akun, tanpa PIN, dan PIN salah, supaya form
     * ini tidak bisa dipakai mengecek nomor mana yang terdaftar.
     */
    public const WRONG = 'Nomor HP atau PIN salah. Belum punya PIN? Masuk dengan nomor resi dulu.';

    public function show(Request $request)
    {
        if (RememberedCustomer::resolve($request)) {
            return redirect()->route('home');
        }

        $locked = $request->session()->get(self::LOCKED);
        if (is_string($locked) && $view = PinController::lockedView($request, $locked, route('customer.login'))) {
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
        $customer = $phone === '' ? null : Customer::where('phone', $phone)->first();

        $result = PinController::checkPin($request, $phone, $customer, $data['pin']);
        if ($result === PinController::LOCKED) {
            $request->session()->put(self::LOCKED, $phone);

            return redirect()->route('customer.login');
        }
        if ($result !== null) {
            return back()->withInput($request->only('phone'))->withErrors([
                'pin' => self::WRONG.' '.PinController::leftNote((int) $result),
            ]);
        }

        $customer->update(['last_seen_at' => now()]);

        return PinController::admit($request, $customer, route('home'));
    }
}
