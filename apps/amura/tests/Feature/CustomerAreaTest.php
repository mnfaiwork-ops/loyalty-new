<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\PointWallet;
use Tests\TestCase;

final class CustomerAreaTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $phone = '081234567890', ?string $name = 'Sari'): Customer
    {
        return Customer::firstOrCreate(['phone' => $phone, 'brand' => config('app.brand')], ['name' => $name]);
    }

    private function asCustomer(Customer $customer): self
    {
        return $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => $customer->brand]));
    }

    public function test_masuk_memasang_cookie_perangkat(): void
    {
        $this->post('/play/start', ['awb' => 'JX-COOKIE', 'phone' => '081234567890'])
            ->assertRedirect()
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_masuk_saat_kesempatan_habis_diarahkan_ke_beranda(): void
    {
        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890']);
        GameSession::firstOrFail()->update(['chance_used' => 2]);

        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890'])
            ->assertRedirect(route('home'));
    }

    public function test_beranda_tanpa_cookie_diarahkan_ke_form_tanpa_pesan_error(): void
    {
        $this->get('/')->assertRedirect(route('play.form'))->assertSessionHasNoErrors();
    }

    public function test_cookie_basi_diarahkan_ke_form_dengan_pesan(): void
    {
        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => 999999, 'brand' => config('app.brand')]))
            ->get('/')
            ->assertRedirect(route('play.form'))
            ->assertSessionHasErrors('awb');

        $this->withCookie(RememberedCustomer::COOKIE, 'bukan-json')->get('/')->assertRedirect(route('play.form'));
    }

    public function test_cookie_brand_lain_ditolak(): void
    {
        $customer = $this->customer();

        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => 'brand-lain']))
            ->get('/')
            ->assertRedirect(route('play.form'));
    }

    public function test_beranda_menampilkan_nama_saldo_dan_kosong(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(1400, 12);

        $this->asCustomer($customer)->get('/')
            ->assertOk()
            ->assertSee('Halo, Sari')
            ->assertSee('1.400')
            ->assertSee('Belum ada hadiah');
    }

    public function test_keluar_menghapus_cookie(): void
    {
        $customer = $this->customer();

        $this->asCustomer($customer)->post('/keluar')
            ->assertRedirect(route('play.form'))
            ->assertCookieExpired(RememberedCustomer::COOKIE);
    }
}
