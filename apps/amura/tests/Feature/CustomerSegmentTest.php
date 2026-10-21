<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PhoneInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\Segment;
use LoyaltyNew\Core\Prizes\TierConfig;
use LoyaltyNew\Core\Settings\SettingsTransfer;
use Tests\TestCase;

/** Kontrol isi kado per jenis pelanggan, baru vs lama (permintaan Fai 2026-10-21). */
final class CustomerSegmentTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '08123456789';

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function open(string $awb, string $phone = self::PHONE): GameSession
    {
        return app(SessionService::class)->open($awb, PhoneInput::normalize($phone));
    }

    private function prize(string $name, string $tier, ?string $segment): PrizeCatalog
    {
        return PrizeCatalog::create([
            'brand' => $this->brand(), 'name' => $name, 'tier' => $tier, 'is_capped' => $tier === 'hadiah_besar',
            'active' => true, 'segment' => $segment,
        ]);
    }

    private function bigPrizeQuota(string $name): void
    {
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(), 'prize_name' => $name, 'cap_units' => 5, 'used_units' => 0,
            'program_start' => now()->subDay()->toDateString(), 'program_end' => now()->addMonth()->toDateString(),
            'release_from' => now()->subDay()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Buka slot 1-3 sebagai voucher dan kembalikan id hadiah yang keluar. */
    private function openVoucherRound(GameSession $session): array
    {
        $session->prizes()->where('round', 1)->whereIn('slot', [1, 2, 3])->update(['tier' => 'voucher', 'prize_catalog_id' => null]);
        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk();

        return $session->prizes()->where('round', 1)->whereIn('slot', [1, 2, 3])->pluck('prize_catalog_id')->unique()->values()->all();
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Fai', 'email' => 'super@x.id', 'password' => 'rahasia',
            'role' => 'super_admin', 'brand_scope' => $this->brand(),
        ]);
    }

    public function test_sesi_pertama_pelanggan_baru_berikutnya_lama_dengan_bobot_masing_masing(): void
    {
        TierConfig::store(['poin' => 0, 'voucher' => 0, 'merch' => 0, 'produk' => 10], $this->brand(), Segment::BARU);
        TierConfig::store(['poin' => 10, 'voucher' => 0, 'merch' => 0, 'produk' => 0], $this->brand(), Segment::LAMA);

        $first = $this->open('JX1');
        $second = $this->open('JX2');

        $this->assertSame(Segment::BARU, $first->segment);
        $this->assertSame(10, $first->tier_config['produk']);
        $this->assertSame(['produk'], $first->prizes()->distinct()->pluck('tier')->all());

        $this->assertSame(Segment::LAMA, $second->segment);
        $this->assertSame(10, $second->tier_config['poin']);
        $this->assertSame(['poin'], $second->prizes()->distinct()->pluck('tier')->all());
    }

    public function test_pelanggan_baru_yang_belum_diatur_ikut_bobot_pelanggan_lama(): void
    {
        TierConfig::store(['poin' => 50, 'voucher' => 40, 'merch' => 5, 'produk' => 5], $this->brand(), Segment::LAMA);

        $this->assertFalse(TierConfig::hasOwn(Segment::BARU));
        $this->assertSame(TierConfig::current(Segment::LAMA), TierConfig::current(Segment::BARU));
        $this->assertSame(40, $this->open('JX1')->tier_config['voucher']);
    }

    public function test_hadiah_hanya_keluar_ke_jenis_pelanggan_yang_diizinkan(): void
    {
        $ongkir = $this->prize('Voucher Gratis Ongkir', 'voucher', Segment::BARU);
        $diskon = $this->prize('Voucher Diskon 50rb', 'voucher', Segment::LAMA);

        $this->assertSame([$ongkir->id], $this->openVoucherRound($this->open('JX1')));
        $this->assertSame([$diskon->id], $this->openVoucherRound($this->open('JX2')));
    }

    public function test_tanpa_hadiah_yang_cocok_kotak_jadi_poin(): void
    {
        $this->prize('Voucher Diskon 50rb', 'voucher', Segment::LAMA);

        $session = $this->open('JX1');
        $this->assertSame([null], $this->openVoucherRound($session));
        $this->assertSame(['poin'], $session->prizes()->where('round', 1)->whereIn('slot', [1, 2, 3])->distinct()->pluck('tier')->all());
    }

    public function test_hadiah_utama_bawaan_pelanggan_lama_bisa_dibuka_untuk_semua(): void
    {
        $this->alwaysPlaceBigPrize();
        $emas = $this->prize('Emas 1 gram', 'hadiah_besar', null);
        $this->bigPrizeQuota('Emas 1 gram');

        $this->assertSame(0, $this->open('JX1', '081200000001')->prizes()->where('tier', 'hadiah_besar')->count());

        $emas->update(['segment' => Segment::SEMUA]);
        $this->assertSame(1, $this->open('JX2', '081200000002')->prizes()->where('tier', 'hadiah_besar')->count());
    }

    public function test_panel_game_menyimpan_bobot_pelanggan_baru_dan_untuk_siapa_hadiahnya(): void
    {
        $admin = $this->superAdmin();
        $prize = $this->prize('Voucher Diskon 50rb', 'voucher', null);

        $this->actingAs($admin)->get('/admin/game')->assertOk()
            ->assertSee('Pelanggan baru')
            ->assertSee('Belum diatur, ikut pelanggan lama');

        $this->actingAs($admin)->post('/admin/game/tiers', ['segment' => 'baru', 'poin' => 60, 'voucher' => 10, 'merch' => 10, 'produk' => 20])
            ->assertSessionHas('ok', 'Bobot tier pelanggan baru disimpan');
        $this->assertSame(['poin' => 60, 'voucher' => 10, 'merch' => 10, 'produk' => 20], TierConfig::current(Segment::BARU));
        $this->assertNotSame(TierConfig::current(Segment::BARU), TierConfig::current(Segment::LAMA));

        $this->actingAs($admin)->post("/admin/game/catalog/{$prize->id}", ['active' => 1, 'segment' => 'lama'])->assertRedirect();
        $this->assertSame(Segment::LAMA, $prize->fresh()->segment);

        $this->actingAs($admin)->post("/admin/game/catalog/{$prize->id}", ['active' => 1, 'segment' => 'vip'])->assertSessionHasErrors('segment');
    }

    public function test_backup_setting_membawa_bobot_pelanggan_baru_dan_untuk_siapa_hadiahnya(): void
    {
        $this->prize('Voucher Diskon 50rb', 'voucher', Segment::LAMA);
        TierConfig::store(['poin' => 60, 'voucher' => 10, 'merch' => 10, 'produk' => 20], $this->brand(), Segment::BARU);

        $file = SettingsTransfer::export($this->brand());
        $this->assertSame(20, $file['tiers_baru']['produk']);
        $this->assertSame(Segment::LAMA, $file['prizes'][0]['segment']);

        $file['prizes'][0]['segment'] = Segment::SEMUA;
        $file['tiers_baru']['produk'] = 30;
        $checked = SettingsTransfer::inspect($file, $this->brand());
        $this->assertContains('Hadiah Voucher Diskon 50rb, untuk: lama jadi semua', $checked['changes']);
        SettingsTransfer::apply($checked['data'], $this->brand());

        $this->assertSame(Segment::SEMUA, PrizeCatalog::where('name', 'Voucher Diskon 50rb')->value('segment'));
        $this->assertSame(30, TierConfig::current(Segment::BARU)['produk']);

        // File lama tanpa isian jenis pelanggan: bobot pelanggan baru tidak disentuh.
        unset($file['tiers_baru']);
        SettingsTransfer::apply(SettingsTransfer::inspect($file, $this->brand())['data'], $this->brand());
        $this->assertSame(30, TierConfig::current(Segment::BARU)['produk']);
    }

    public function test_landing_page_tidak_menyebut_aturan_selain_hadiah_utama_pelanggan_lama(): void
    {
        $this->prize('Voucher Diskon 50rb', 'voucher', Segment::LAMA);
        $this->prize('Philips Air Fryer', 'hadiah_besar', null);
        $this->prize('Emas Antam', 'hadiah_besar', Segment::SEMUA);

        $html = $this->get('/cara-main')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Hadiah utama, mulai pesanan kedua'));
        $this->assertStringNotContainsString('Pelanggan lama', $html);
        $this->assertStringNotContainsString('Pelanggan baru', $html);
    }
}
