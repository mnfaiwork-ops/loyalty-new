<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\GameControls;
use Tests\TestCase;

/** Landing page /cara-main ikut setting game, tanpa edit manual (permintaan Fai 2026-10-21). */
final class GuideTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function prize(string $name, string $tier, bool $active = true): void
    {
        PrizeCatalog::create(['brand' => $this->brand(), 'name' => $name, 'tier' => $tier, 'is_capped' => $tier === 'hadiah_besar', 'active' => $active]);
    }

    public function test_halaman_terbuka_tanpa_login_dengan_tombol_ke_play(): void
    {
        $this->get('/cara-main')->assertOk()
            ->assertSee('Cara buka Kejutan Kado')
            ->assertSee('href="'.route('play.form').'"', false)
            ->assertSee('img/cara-main/'.$this->brand().'/papan.webp', false)
            ->assertSee(config('play.themes.'.$this->brand().'.logo.src'), false);
    }

    public function test_angka_poin_ikut_setting(): void
    {
        LoyaltySettings::store(['min_redeem' => 1500, 'validity_months' => 6] + LoyaltySettings::current());

        $this->get('/cara-main')->assertOk()
            ->assertSee('Minimal tukar 1.500 poin')
            ->assertSee('Berlaku 6 bulan');
    }

    public function test_daftar_hadiah_hanya_yang_aktif_dan_poin_digabung(): void
    {
        $this->prize('Poin 25.000', 'poin');
        $this->prize('Poin 50.000', 'poin');
        $this->prize('Voucher Gratis Ongkir', 'voucher');
        $this->prize('Philips Air Fryer', 'hadiah_besar');
        $this->prize('Hadiah Nonaktif', 'merch', false);

        $response = $this->get('/cara-main')->assertOk()
            ->assertSee('Voucher Gratis Ongkir')
            ->assertSee('Philips Air Fryer')
            ->assertSee('Hadiah utama')
            ->assertDontSee('Hadiah Nonaktif')
            ->assertDontSee('Poin 25.000');

        $this->assertSame(1, substr_count($response->getContent(), 'nilainya ikut nilai belanja'));
    }

    public function test_katalog_bulan_berjalan_dan_item_nonaktif_disembunyikan(): void
    {
        $catalog = RewardCatalog::create(['brand' => $this->brand(), 'period' => now()->format('Y-m'), 'published_at' => now()]);
        $catalog->items()->create(['name' => 'Pouch Uji', 'points_cost' => 1000, 'active' => true, 'sort' => 1]);
        $catalog->items()->create(['name' => 'Item Disembunyikan', 'points_cost' => 900, 'active' => false, 'sort' => 2]);

        $this->get('/cara-main')->assertOk()
            ->assertSee('Katalog tukar poin, '.now()->translatedFormat('F Y'))
            ->assertSee('Pouch Uji')
            ->assertSee('1.000')
            ->assertDontSee('Item Disembunyikan');
    }

    public function test_tanpa_katalog_dan_hadiah_menampilkan_keadaan_kosong(): void
    {
        $this->get('/cara-main')->assertOk()
            ->assertSee('Katalog bulan ini belum terbit.')
            ->assertSee('Daftar hadiah sedang disiapkan.');
    }

    public function test_periode_ikut_kendali_gatcha(): void
    {
        GameControls::store(['paused' => false, 'big_prize_chance' => 20, 'order_from' => '2026-10-27', 'order_until' => '2026-12-31']);

        $this->get('/cara-main')->assertOk()
            ->assertSee('Pesanan yang dibuat 27 Oktober sampai 31 Desember 2026.')
            ->assertSee('Berlaku untuk pesanan mulai 27 Oktober 2026.');
    }

    public function test_tanpa_periode_baris_periode_disembunyikan(): void
    {
        $this->get('/cara-main')->assertOk()
            ->assertDontSee('<dt>Periode</dt>', false)
            ->assertDontSee('Berlaku untuk pesanan mulai');
    }
}
