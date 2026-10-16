<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LoyaltyNew\Core\Content\SiteContent;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Models\Customer;
use Tests\TestCase;

/** Teks halaman resi dan pop up promo dari /admin/tampilan (2026-10-16). */
final class DisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function staff(string $role = 'game_admin'): User
    {
        return User::firstOrCreate(['email' => $role.'@x.id'], [
            'name' => 'Staf', 'password' => 'rahasia-panjang', 'role' => $role, 'brand_scope' => config('app.brand'),
        ]);
    }

    private function savePopup(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->staff())->post('/admin/tampilan/popup', $data);
    }

    public function test_halaman_resi_memakai_teks_bawaan(): void
    {
        $this->get('/play')->assertOk()
            ->assertSee('<h1>Paketmu sudah sampai?</h1>', false)
            ->assertSee('Masukkan nomor resi dan nomor HP untuk membuka Gatcha-mu.')
            ->assertDontSee('<dialog', false);
    }

    public function test_admin_mengganti_teks_halaman_resi(): void
    {
        $this->actingAs($this->staff())->post('/admin/tampilan/teks', [
            'play_title' => 'Yuk rebut Antam sekarang!', 'play_lead' => 'Masukkan resi, buka kado, menangkan emas.',
        ])->assertRedirect();
        auth()->logout();

        $this->get('/play')->assertOk()
            ->assertSee('<h1>Yuk rebut Antam sekarang!</h1>', false)
            ->assertSee('Masukkan resi, buka kado, menangkan emas.');

        // Dikosongkan: kembali ke teks bawaan.
        $this->actingAs($this->staff())->post('/admin/tampilan/teks', ['play_title' => '', 'play_lead' => ''])->assertRedirect();
        $this->assertSame(SiteContent::DEFAULT_TITLE, SiteContent::current()['play_title']);
    }

    public function test_pop_up_hanya_muncul_di_halaman_yang_dipilih(): void
    {
        $this->savePopup([
            'active' => '1', 'image' => UploadedFile::fake()->image('antam.jpg', 720, 900),
            'alt' => 'Rebut emas Antam', 'link' => 'https://reglow.co.id/promo', 'pages' => ['play'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        auth()->logout();

        $image = SiteContent::current()['popup']['image'];
        Storage::disk('public')->assertExists($image);

        $this->get('/play')->assertOk()
            ->assertSee('<dialog class="promo"', false)
            ->assertSee('href="https://reglow.co.id/promo"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('alt="Rebut emas Antam"', false);
        $this->get('/masuk')->assertOk()->assertDontSee('<dialog', false);
    }

    public function test_pop_up_di_beranda_pelanggan(): void
    {
        $this->savePopup(['active' => '1', 'image' => UploadedFile::fake()->image('a.jpg'), 'alt' => 'Promo Beranda', 'pages' => ['home']]);
        auth()->logout();
        $customer = Customer::create(['phone' => '081234567890', 'brand' => config('app.brand')]);
        CustomerPin::set($customer, '2580');

        $this->withCookie(RememberedCustomer::COOKIE, RememberedCustomer::payload($customer->fresh()))
            ->get('/')->assertOk()->assertSee('alt="Promo Beranda"', false);
        $this->get('/play')->assertOk()->assertDontSee('<dialog', false);
    }

    public function test_pop_up_tanpa_link_menutup_saat_diklik(): void
    {
        $this->savePopup([
            'active' => '1', 'image' => UploadedFile::fake()->image('promo.png'), 'alt' => 'Promo', 'pages' => ['masuk'],
        ])->assertRedirect();
        auth()->logout();

        $this->get('/masuk')->assertOk()
            ->assertSee('<button type="button" class="promo-media" data-close>', false)
            ->assertDontSee('class="promo-media" href=', false);
    }

    public function test_pop_up_nonaktif_tidak_tampil_dan_versi_naik_tiap_simpan(): void
    {
        $this->savePopup(['active' => '1', 'image' => UploadedFile::fake()->image('a.jpg'), 'alt' => 'A', 'pages' => ['play']]);
        $this->savePopup(['alt' => 'A', 'pages' => ['play']])->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertSame(2, SiteContent::current()['popup']['version']);
        $this->get('/play')->assertOk()->assertDontSee('<dialog', false);
    }

    public function test_validasi_pop_up(): void
    {
        $this->savePopup(['active' => '1', 'alt' => 'A', 'pages' => ['play']])->assertSessionHasErrors('image');
        $this->savePopup([
            'active' => '1', 'image' => UploadedFile::fake()->image('a.jpg'), 'alt' => 'A', 'pages' => ['play'], 'link' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('link');
        $this->savePopup(['active' => '1', 'image' => UploadedFile::fake()->image('a.jpg'), 'alt' => 'A'])->assertSessionHasErrors('pages');
        $this->savePopup([
            'active' => '1', 'image' => UploadedFile::fake()->create('a.svg', 10, 'image/svg+xml'), 'alt' => 'A', 'pages' => ['play'],
        ])->assertSessionHasErrors('image');

        $this->assertNull(SiteContent::current()['popup']['image']);
    }

    public function test_ganti_gambar_menghapus_gambar_lama(): void
    {
        $this->savePopup(['active' => '1', 'image' => UploadedFile::fake()->image('a.jpg'), 'alt' => 'A', 'pages' => ['home']]);
        $old = SiteContent::current()['popup']['image'];

        $this->savePopup(['active' => '1', 'image' => UploadedFile::fake()->image('b.jpg'), 'alt' => 'B', 'pages' => ['home']]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(SiteContent::current()['popup']['image']);
    }

    public function test_hanya_game_admin_dan_super_admin(): void
    {
        $this->actingAs($this->staff('cs'))->get('/admin/tampilan')->assertForbidden();
        $this->actingAs($this->staff('super_admin'))->get('/admin/tampilan')->assertOk()->assertSee('Pop up promo');
        $this->actingAs($this->staff())->get('/admin/tampilan')->assertOk();
    }
}
