<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Models\Customer;
use Tests\TestCase;

/** Perbaikan audit keamanan 2026-10-16. */
final class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '081234567890';

    private function customerWithPin(string $pin = '2580'): Customer
    {
        $customer = Customer::create(['phone' => self::PHONE, 'brand' => config('app.brand')]);
        CustomerPin::set($customer, $pin);

        return $customer->fresh();
    }

    private function signInFrom(string $ip, string $pin): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/masuk', ['phone' => self::PHONE, 'pin' => $pin]);
    }

    private function staff(string $role, string $password = 'rahasia-panjang'): User
    {
        return User::create([
            'name' => 'Staf '.$role, 'email' => $role.'@x.id', 'password' => $password,
            'role' => $role, 'brand_scope' => config('app.brand'),
        ]);
    }

    public function test_gate_selain_stub_memakai_gate_nyata(): void
    {
        config(['markom.gate' => 'markom ']);

        $this->assertInstanceOf(MarkomApiOrderGate::class, app(OrderGate::class));
    }

    public function test_gate_stub_ditolak_di_produksi(): void
    {
        config(['markom.gate' => 'stub']);
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('MARKOM_GATE=stub tidak boleh dipakai saat APP_ENV=production.');
            app(OrderGate::class);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_pin_salah_dari_satu_jaringan_tidak_mengunci_jaringan_lain(): void
    {
        $this->customerWithPin();

        for ($i = 0; $i < 5; $i++) {
            $this->signInFrom('10.0.0.1', '0001');
        }
        $this->signInFrom('10.0.0.1', '2580')->assertRedirect(route('customer.login'));

        // Pemilik di jaringan lain tetap bisa masuk.
        $this->signInFrom('10.0.0.2', '2580')->assertRedirect(route('home'));
    }

    public function test_batas_harian_mengunci_lintas_jaringan_dan_dibuka_reset_cs(): void
    {
        $customer = $this->customerWithPin();

        foreach (['10.0.1.1', '10.0.1.2', '10.0.1.3', '10.0.1.4'] as $ip) {
            for ($i = 0; $i < 5; $i++) {
                $this->signInFrom($ip, '0001');
            }
        }

        $this->signInFrom('10.0.1.5', '2580')->assertRedirect(route('customer.login'));
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])->get('/masuk')
            ->assertOk()->assertSee('Coba lagi dalam 24 jam, atau hubungi CS.');

        $this->actingAs($this->staff('cs'))->post("/admin/customers/{$customer->id}/reset-pin");
        $temporary = session('temporary_pin')['pin'];
        auth()->logout();

        $this->signInFrom('10.0.1.5', $temporary)->assertRedirect(route('pin.create'));
    }

    public function test_proxy_yang_dipercaya_membedakan_ip_pengunjung(): void
    {
        $this->customerWithPin();
        config(['trustedproxy.proxies' => '127.0.0.1']);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.1')->post('/masuk', ['phone' => self::PHONE, 'pin' => '0001']);
        }

        $this->withHeader('X-Forwarded-For', '203.0.113.2')
            ->post('/masuk', ['phone' => self::PHONE, 'pin' => '2580'])
            ->assertRedirect(route('home'));
    }

    public function test_tanpa_proxy_dipercaya_header_forwarded_diabaikan(): void
    {
        $this->customerWithPin();

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Forwarded-For', '203.0.113.1')->post('/masuk', ['phone' => self::PHONE, 'pin' => '0001']);
        }

        // Header palsu tidak bisa dipakai keluar dari kunci.
        $this->withHeader('X-Forwarded-For', '203.0.113.2')
            ->post('/masuk', ['phone' => self::PHONE, 'pin' => '2580'])
            ->assertRedirect(route('customer.login'));
    }

    public function test_session_diganti_setelah_pin_cocok(): void
    {
        $this->customerWithPin();
        $this->post('/play/start', ['awb' => 'JX-SES', 'phone' => self::PHONE])->assertRedirect(route('pin.show'));
        $before = session()->getId();

        $this->post('/pin', ['pin' => '2580'])->assertRedirect();

        $this->assertNotSame($before, session()->getId());
    }

    public function test_keluar_mencabut_cookie_yang_tersalin(): void
    {
        $customer = $this->customerWithPin();
        $cookie = RememberedCustomer::payload($customer);

        $this->withCookie(RememberedCustomer::COOKIE, $cookie)->get('/')->assertOk();
        $this->withCookie(RememberedCustomer::COOKIE, $cookie)->post('/keluar')
            ->assertRedirect(route('play.form'));

        // Salinan cookie yang sama di perangkat lain tidak berlaku lagi.
        $this->withCookie(RememberedCustomer::COOKIE, $cookie)->get('/')->assertRedirect(route('customer.login'));
        $this->assertSame(1, $customer->fresh()->device_version);
    }

    public function test_header_keamanan_terpasang(): void
    {
        $this->get('/play')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_login_admin_dikunci_per_email(): void
    {
        $user = $this->staff('cs');

        // Dari IP berbeda supaya yang diuji kunci per email, bukan batas per IP.
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.{$i}"])
                ->post('/login', ['email' => $user->email, 'password' => 'salah-'.$i]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.9'])
            ->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.']);
        $this->assertGuest();

        $this->travel(16)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.9'])
            ->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_staf_baru_minimal_12_karakter(): void
    {
        $this->actingAs($this->staff('super_admin'))->post('/admin/users', [
            'name' => 'CS Baru', 'email' => 'cs-baru@x.id', 'password' => 'pendek12345',
            'role' => 'cs', 'brand_scope' => config('app.brand'),
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'cs-baru@x.id']);
    }
}
