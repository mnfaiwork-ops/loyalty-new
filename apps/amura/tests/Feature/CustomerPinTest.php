<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\SignInController;
use App\Http\Middleware\RememberedCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

final class CustomerPinTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '081234567890';

    private function startWithResi(string $awb = 'JX-PIN'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/play/start', ['awb' => $awb, 'phone' => self::PHONE]);
    }

    private function customerWithPin(string $pin = '2580'): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => self::PHONE, 'brand' => config('app.brand')]);
        CustomerPin::set($customer->fresh(), $pin);

        return $customer->fresh();
    }

    private function staff(string $role = 'cs'): User
    {
        return User::create([
            'name' => 'Staf '.$role, 'email' => $role.'@x.id', 'password' => 'rahasia',
            'role' => $role, 'brand_scope' => config('app.brand'),
        ]);
    }

    public function test_pelanggan_baru_membuat_pin_dua_kali_lalu_main(): void
    {
        $this->startWithResi()->assertRedirect(route('pin.create'));
        $this->get('/pin/buat')->assertOk()->assertSee('Buat PIN');

        $this->post('/pin/buat', ['pin' => '2580', 'pin_confirmation' => '2581'])
            ->assertSessionHasErrors('pin_confirmation');

        $token = GameSession::firstOrFail()->session_token;
        $this->post('/pin/buat', ['pin' => '2580', 'pin_confirmation' => '2580'])
            ->assertRedirect(route('play.board', $token))
            ->assertCookie(RememberedCustomer::COOKIE);

        $customer = Customer::firstOrFail();
        $this->assertTrue(CustomerPin::matches($customer, '2580'));
        $this->assertNotSame('2580', $customer->pin_hash);
    }

    public function test_pin_yang_mudah_ditebak_ditolak(): void
    {
        $this->startWithResi();

        foreach (['1234', '0000', '9876', '12a4', '123'] as $pin) {
            $this->post('/pin/buat', ['pin' => $pin, 'pin_confirmation' => $pin])->assertSessionHasErrors('pin');
        }

        $this->assertNull(Customer::firstOrFail()->pin_hash);
    }

    public function test_layar_pin_tanpa_resi_dikembalikan_ke_form(): void
    {
        $this->get('/pin')->assertRedirect(route('play.form'));
        $this->get('/pin/buat')->assertRedirect(route('play.form'));
        $this->post('/pin/buat', ['pin' => '2580', 'pin_confirmation' => '2580'])->assertRedirect(route('play.form'));
    }

    public function test_resi_baru_pelanggan_lama_minta_pin_bukan_buat_pin(): void
    {
        $this->customerWithPin();

        $this->startWithResi()->assertRedirect(route('pin.show'));

        // Yang hanya tahu resi + HP tidak bisa menimpa PIN pemilik.
        $this->get('/pin/buat')->assertRedirect(route('pin.show'));
        $this->post('/pin/buat', ['pin' => '7391', 'pin_confirmation' => '7391'])->assertRedirect(route('pin.show'));
        $this->assertTrue(CustomerPin::matches(Customer::firstOrFail(), '2580'));

        $this->post('/pin', ['pin' => '1111'])->assertSessionHasErrors('pin');

        $token = GameSession::firstOrFail()->session_token;
        $this->post('/pin', ['pin' => '2580'])
            ->assertRedirect(route('play.board', $token))
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_perangkat_yang_diingat_tidak_ditanya_pin_lagi(): void
    {
        $customer = $this->customerWithPin();

        $response = $this->withCookie(RememberedCustomer::COOKIE, RememberedCustomer::payload($customer))->startWithResi();

        $response->assertRedirect(route('play.board', GameSession::firstOrFail()->session_token));
    }

    public function test_lima_kali_pin_salah_dikunci(): void
    {
        $this->customerWithPin();
        $this->startWithResi();

        // Sisa percobaan disebut supaya kunci tidak datang tiba-tiba.
        $this->post('/pin', ['pin' => '0001'])
            ->assertSessionHasErrors(['pin' => 'PIN salah. Cek lagi angkanya. Sisa 4 kali sebelum dikunci 15 menit.']);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/pin', ['pin' => '0001']);
        }
        $this->post('/pin', ['pin' => '0001'])->assertRedirect(route('pin.show'));

        $this->get('/pin')->assertOk()
            ->assertSee('Coba lagi sebentar, ya')
            ->assertSee('Coba lagi dalam 15 menit, atau hubungi CS.')
            ->assertSee('Yang dikunci hanya masuk dengan nomor', false);

        // PIN benar pun ditolak selama terkunci, di dua pintu masuk.
        $this->post('/pin', ['pin' => '2580'])->assertRedirect(route('pin.show'));
        $this->post('/masuk', ['phone' => self::PHONE, 'pin' => '2580'])->assertRedirect(route('customer.login'));
        $this->get('/masuk')->assertOk()->assertSee('Coba lagi sebentar, ya');

        // Kunci habis: layar masuk kembali seperti biasa.
        $this->travel(16)->minutes();
        $this->get('/masuk')->assertOk()->assertSee('Masuk dengan nomor HP');
        $this->post('/masuk', ['phone' => self::PHONE, 'pin' => '2580'])->assertRedirect(route('home'));
    }

    public function test_masuk_dengan_nomor_hp_dan_pin(): void
    {
        $this->customerWithPin();

        $this->get('/masuk')->assertOk()->assertSee('Masuk dengan nomor HP');

        $this->post('/masuk', ['phone' => '812 3456 7890', 'pin' => '2580'])
            ->assertRedirect(route('home'))
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_masuk_nomor_tanpa_pin_dan_pin_salah_mendapat_pesan_yang_sama(): void
    {
        // Nomor yang tidak terdaftar: pesan dan sisa percobaan sama dengan PIN salah,
        // supaya form ini tidak bisa dipakai mengecek nomor mana yang punya akun.
        $expected = SignInController::WRONG.' Sisa 4 kali sebelum dikunci 15 menit.';
        $this->post('/masuk', ['phone' => '081299990000', 'pin' => '2580'])
            ->assertSessionHasErrors(['pin' => $expected])
            ->assertSessionDoesntHaveErrors('phone');

        Customer::create(['phone' => '081288880000', 'brand' => config('app.brand')]);
        $this->post('/masuk', ['phone' => '081288880000', 'pin' => '2580'])
            ->assertSessionHasErrors(['pin' => $expected]);

        $this->customerWithPin();
        $this->post('/masuk', ['phone' => self::PHONE, 'pin' => '0001'])
            ->assertSessionHasErrors(['pin' => $expected]);
    }

    public function test_nomor_tidak_terdaftar_juga_dikunci_seperti_nomor_terdaftar(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['phone' => '081299990000', 'pin' => '2580']);
        }

        $this->get('/masuk')->assertOk()->assertSee('Coba lagi sebentar, ya')->assertSee('0812••••0000', false);
    }

    public function test_cookie_lama_tanpa_pin_diminta_membuat_pin(): void
    {
        $customer = Customer::create(['phone' => self::PHONE, 'brand' => config('app.brand')]);

        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => $customer->brand]))
            ->get('/')
            ->assertRedirect(route('pin.create'));

        $this->post('/pin/buat', ['pin' => '2580', 'pin_confirmation' => '2580'])
            ->assertRedirect(route('home'))
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_cs_reset_pin_memberi_pin_sementara_yang_wajib_diganti(): void
    {
        $customer = $this->customerWithPin();
        $oldCookie = RememberedCustomer::payload($customer);

        $this->actingAs($this->staff())->get('/admin/customers?q=0812345')
            ->assertOk()
            ->assertSee('0812 3456 7890');

        $this->post("/admin/customers/{$customer->id}/reset-pin")
            ->assertRedirect()
            ->assertSessionHas('temporary_pin');
        $temporary = session('temporary_pin')['pin'];
        $this->assertStringContainsString('wa.me/6281234567890', session('temporary_pin')['wa']);
        $this->assertStringContainsString("akun {$this->brandName()} kamu: {$temporary}.", session('temporary_pin')['message']);

        // Panel kanan menampilkan PIN, isi pesan, dan catatan log (desain 3l); setiap reset disimpan.
        $this->get('/admin/customers?q='.self::PHONE)->assertOk()
            ->assertSee('Hanya tampil sekali.')
            ->assertSee('PESAN YANG TERKIRIM')
            ->assertSee('Tercatat di log: Staf cs');
        $this->assertDatabaseHas('customer_pin_resets', ['customer_id' => $customer->id, 'brand' => config('app.brand')]);

        $customer->refresh();
        $this->assertTrue($customer->pin_must_change);
        $this->assertFalse(CustomerPin::matches($customer, '2580'));

        // Perangkat yang diingat dengan PIN lama harus masuk lagi.
        $this->withCookie(RememberedCustomer::COOKIE, $oldCookie)->get('/')->assertRedirect(route('customer.login'));

        $this->post('/masuk', ['phone' => self::PHONE, 'pin' => $temporary])->assertRedirect(route('pin.create'));
        $this->get('/pin/buat')->assertOk()->assertSee('Ganti PIN sementara')->assertSee('Tinggal satu langkah.');
        $this->post('/pin/buat', ['pin' => '7391', 'pin_confirmation' => '7391'])
            ->assertRedirect(route('home'))
            ->assertCookie(RememberedCustomer::COOKIE);

        $this->assertFalse($customer->fresh()->pin_must_change);
        $this->assertTrue(CustomerPin::matches($customer->fresh(), '7391'));
    }

    public function test_pin_sementara_lewat_resi_juga_wajib_diganti(): void
    {
        $customer = $this->customerWithPin();
        $temporary = CustomerPin::issueTemporary($customer, $this->staff());

        $this->startWithResi()->assertRedirect(route('pin.show'));
        $this->post('/pin', ['pin' => $temporary])->assertRedirect(route('pin.create'));
        $this->post('/pin/buat', ['pin' => '7391', 'pin_confirmation' => '7391'])
            ->assertRedirect(route('play.board', GameSession::firstOrFail()->session_token));
    }

    public function test_cs_tidak_bisa_reset_pelanggan_tanpa_pin_dan_nomor_asing_diberi_saran(): void
    {
        $customer = Customer::create(['phone' => self::PHONE, 'brand' => config('app.brand')]);

        $this->actingAs($this->staff())->get('/admin/customers?q='.self::PHONE)->assertOk()->assertSee('Belum punya PIN');
        $this->post("/admin/customers/{$customer->id}/reset-pin")->assertSessionHasErrors('pin');
        $this->assertNull($customer->fresh()->pin_hash);
        $this->assertDatabaseCount('customer_pin_resets', 0);

        $this->get('/admin/customers?q=081299990000')->assertOk()
            ->assertSee('Nomor 0812 9999 0000 tidak ditemukan di '.$this->brandName())
            ->assertSee('Cek lagi angka dari profil chat.');
        $this->get('/admin/customers')->assertOk()->assertSee('Ketik nomor HP dari chat WhatsApp pelanggan.');
    }

    private function brandName(): string
    {
        return config('play.themes.'.config('app.brand').'.name');
    }

    public function test_reset_pin_hanya_untuk_cs(): void
    {
        $customer = $this->customerWithPin();

        $this->actingAs($this->staff('finance'))
            ->post("/admin/customers/{$customer->id}/reset-pin")
            ->assertForbidden();

        $this->assertFalse($customer->fresh()->pin_must_change);
    }
}
