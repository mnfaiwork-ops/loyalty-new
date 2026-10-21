<?php

namespace Tests;

use App\Http\Middleware\RememberedCustomer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Prizes\GameControls;

abstract class TestCase extends BaseTestCase
{
    /** Main sebagai pemilik sesi: pelanggan punya PIN dan perangkat ini diingat. */
    protected function playAs(GameSession $session): static
    {
        $customer = Customer::withoutGlobalScopes()->findOrFail($session->customer_id);
        if ($customer->pin_hash === null) {
            CustomerPin::set($customer, '8264');
        }

        // postJson hanya mengirim cookie kalau withCredentials aktif.
        return $this->withCredentials()
            ->withCookie(RememberedCustomer::COOKIE, RememberedCustomer::payload($customer->refresh()));
    }

    /**
     * Hadiah utama hanya untuk pesanan kedua dst. (2026-10-21): beri nomor ini satu
     * pesanan sebelumnya yang sudah selesai dimainkan.
     */
    protected function previousOrder(string $phone): void
    {
        $session = app(\LoyaltyNew\Core\Gacha\SessionService::class)
            ->open('JX-SEBELUMNYA-'.substr(md5($phone), 0, 6), \App\Support\PhoneInput::normalize($phone));
        $session->update(['chance_used' => $session->chance_total]);
        $session->prizes()->delete();
    }

    /** Tes hadiah utama: taruh hadiah utama di setiap papan selama kuota ada. */
    protected function alwaysPlaceBigPrize(): void
    {
        GameControls::store(['paused' => false, 'big_prize_chance' => 100], (string) config('app.brand'));
    }
}
