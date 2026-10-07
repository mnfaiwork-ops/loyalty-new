<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

final class CustomerAreaTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $phone = '081234567890', ?string $name = 'Sari'): Customer
    {
        $customer = Customer::firstOrCreate(['phone' => $phone, 'brand' => config('app.brand')], ['name' => $name]);
        if ($customer->fresh()->pin_hash === null) {
            CustomerPin::set($customer->fresh(), '2580');
        }

        return $customer->fresh();
    }

    private function asCustomer(Customer $customer): self
    {
        return $this->withCookie(RememberedCustomer::COOKIE, RememberedCustomer::payload($customer->fresh()));
    }

    public function test_masuk_memasang_cookie_perangkat_setelah_pin_dibuat(): void
    {
        $this->post('/play/start', ['awb' => 'JX-COOKIE', 'phone' => '081234567890'])
            ->assertRedirect(route('pin.create'))
            ->assertCookieMissing(RememberedCustomer::COOKIE);

        $session = GameSession::firstOrFail();
        $this->post('/pin/buat', ['pin' => '2580', 'pin_confirmation' => '2580'])
            ->assertRedirect(route('play.board', $session->session_token))
            ->assertCookie(RememberedCustomer::COOKIE);
    }

    public function test_masuk_saat_kesempatan_habis_diarahkan_ke_beranda(): void
    {
        $this->customer();
        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890']);
        GameSession::firstOrFail()->update(['chance_used' => 2]);

        $this->post('/play/start', ['awb' => 'JX-HABIS', 'phone' => '081234567890'])
            ->assertRedirect(route('pin.show'));
        $this->post('/pin', ['pin' => '2580'])->assertRedirect(route('home'));
    }

    public function test_beranda_tanpa_cookie_diarahkan_ke_form_tanpa_pesan_error(): void
    {
        $this->get('/')->assertRedirect(route('play.form'))->assertSessionHasNoErrors();
    }

    public function test_cookie_basi_diarahkan_ke_form_dengan_pesan(): void
    {
        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => 999999, 'brand' => config('app.brand')]))
            ->get('/')
            ->assertRedirect(route('customer.login'))
            ->assertSessionHasErrors('phone');

        $this->withCookie(RememberedCustomer::COOKIE, 'bukan-json')->get('/')->assertRedirect(route('customer.login'));
    }

    public function test_cookie_brand_lain_ditolak(): void
    {
        $customer = $this->customer();

        $this->withCookie(RememberedCustomer::COOKIE, json_encode(['id' => $customer->id, 'brand' => 'brand-lain', 'pin' => $customer->pin_version]))
            ->get('/')
            ->assertRedirect(route('customer.login'));
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

    private function claimFor(Customer $customer, string $status = 'belum_redeem', string $name = 'Serum 20 ml'): Claim
    {
        $claim = Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => $name,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => now()->addDays(14),
        ]);

        return $claim;
    }

    public function test_hadiahku_memisahkan_tab(): void
    {
        $customer = $this->customer();
        $this->claimFor($customer, 'belum_redeem', 'Pouch Exclusive');
        $this->claimFor($customer, 'diproses', 'Gold Serum');
        $this->claimFor($customer, 'hangus', 'Voucher Lama');

        $this->asCustomer($customer)->get('/hadiah')->assertOk()->assertSee('Pouch Exclusive')->assertDontSee('Gold Serum');
        $this->asCustomer($customer)->get('/hadiah?tab=proses')->assertOk()->assertSee('Gold Serum')->assertDontSee('Pouch Exclusive');
        $this->asCustomer($customer)->get('/hadiah?tab=selesai')->assertOk()->assertSee('Voucher Lama');
    }

    public function test_voucher_menampilkan_nama_dan_nomor_pemenang(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Sari')
            ->assertSee('081234567890')
            ->assertSee($claim->claim_code)
            ->assertSee('Redeem sekarang');
    }

    public function test_voucher_milik_pelanggan_lain_404(): void
    {
        $owner = $this->customer();
        $claim = $this->claimFor($owner);
        $other = $this->customer('081200000999', 'Budi');

        $this->asCustomer($other)->get("/hadiah/{$claim->claim_code}")->assertNotFound();
        $this->asCustomer($other)->post("/hadiah/{$claim->claim_code}/redeem")->assertNotFound();
    }

    public function test_redeem_mengubah_status_dan_menampilkan_link_wa(): void
    {
        config(['loyalty.cs_whatsapp' => '0811 4000 2626']);
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem")
            ->assertRedirect(route('rewards.show', $claim->claim_code));
        $this->assertSame('diajukan', $claim->fresh()->status);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('https://wa.me/6281140002626', false)
            ->assertSee('Redeem diajukan');
    }

    public function test_redeem_ganda_tidak_error(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);

        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem");
        $this->asCustomer($customer)->post("/hadiah/{$claim->claim_code}/redeem")
            ->assertRedirect(route('rewards.show', $claim->claim_code));

        // Klaim dibuat langsung (tanpa event awal), jadi hanya ada satu event: diajukan.
        $this->assertSame(1, $claim->events()->count());
    }

    public function test_tanpa_nomor_cs_tombol_wa_disembunyikan(): void
    {
        config(['loyalty.cs_whatsapp' => null]);
        $customer = $this->customer();
        $claim = $this->claimFor($customer, 'diajukan');

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Nomor CS belum diatur')
            ->assertDontSee('wa.me', false);
    }

    public function test_klaim_ditolak_menampilkan_alasan(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);
        $claim->update(['rejected_reason' => 'Nomor pengirim tidak cocok']);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Kirim dari nomor pemenang');
    }

    private function catalogItem(string $name = 'Voucher Free Ongkir', int $cost = 1000, ?int $stock = null)
    {
        $catalog = RewardCatalog::firstOrCreate(
            ['brand' => config('app.brand'), 'period' => now()->format('Y-m')],
            ['published_at' => now()],
        );

        return $catalog->items()->create(['name' => $name, 'points_cost' => $cost, 'stock' => $stock]);
    }

    public function test_katalog_menampilkan_item_dan_kekurangan_poin(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(600, 12);
        $this->catalogItem('Pouch Exclusive', 500);
        $this->catalogItem('Voucher Free Ongkir', 1000);

        $this->asCustomer($customer)->get('/katalog')
            ->assertOk()
            ->assertSee('Pouch Exclusive')
            ->assertSee('Kurang 400 poin');
    }

    public function test_katalog_kosong_menampilkan_pesan(): void
    {
        $this->asCustomer($this->customer())->get('/katalog')->assertOk()->assertSee('Katalog bulan ini segera hadir');
    }

    public function test_tukar_berhasil_arah_ke_voucher(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(1200, 12);
        $item = $this->catalogItem();

        $response = $this->asCustomer($customer)->post("/katalog/{$item->id}/tukar");

        $claim = Claim::firstOrFail();
        $response->assertRedirect(route('rewards.show', $claim->claim_code));
        $this->assertSame(200, (new PointWallet($customer))->balance());
    }

    public function test_tukar_poin_kurang_kembali_dengan_pesan(): void
    {
        $customer = $this->customer();
        (new PointWallet($customer))->grant(600, 12);
        $item = $this->catalogItem();

        $this->asCustomer($customer)->from('/katalog')->post("/katalog/{$item->id}/tukar")
            ->assertRedirect('/katalog')
            ->assertSessionHasErrors('catalog');

        $this->assertSame(0, Claim::count());
    }

    public function test_masuk_saat_masa_main_habis_tetap_ke_beranda(): void
    {
        $this->customer();
        $this->post('/play/start', ['awb' => 'JX-LAMA', 'phone' => '081234567890']);
        GameSession::firstOrFail()->update(['expires_at' => now()->subDay()]);

        $this->post('/play/start', ['awb' => 'JX-LAMA', 'phone' => '081234567890'])
            ->assertRedirect(route('pin.show'));
        $this->post('/pin', ['pin' => '2580'])
            ->assertRedirect(route('home'))
            ->assertCookie(RememberedCustomer::COOKIE)
            ->assertSessionHas('ok');

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('play_attempts')->count());
    }

    public function test_voucher_lewat_batas_menampilkan_keterangan_hangus(): void
    {
        $customer = $this->customer();
        $claim = $this->claimFor($customer);
        $claim->update(['expires_at' => now()->subHour()]);

        $this->asCustomer($customer)->get("/hadiah/{$claim->claim_code}")
            ->assertOk()
            ->assertSee('Batas redeem sudah lewat')
            ->assertDontSee('Redeem sekarang');
    }
}
