<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberedCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Gate\GateResult;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Gate\SoscomOrderStatusSource;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Prizes\BigPrizeQuota;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Seeding\LaunchCatalog;
use Tests\TestCase;

/** Kendali hadiah dari feedback testing Fai 2026-10-15. */
final class PrizeControlsTest extends TestCase
{
    use RefreshDatabase;

    private function start(string $awb = 'JX123', string $phone = '08123456789'): GameSession
    {
        $this->post('/play/start', ['awb' => $awb, 'phone' => $phone])->assertRedirect();

        return GameSession::where('awb', $awb)->firstOrFail();
    }

    private function forceTiers(GameSession $session, array $slots, string $tier): void
    {
        $session->prizes()->whereIn('slot', $slots)->update(['tier' => $tier, 'prize_catalog_id' => null]);
    }

    private function openRound(GameSession $session): array
    {
        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        return $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk()->json('revealed');
    }

    private function bigPrize(int $cap, string $start, string $end, int $used = 0): PrizeCatalog
    {
        $prize = PrizeCatalog::create([
            'brand' => config('app.brand'), 'name' => 'Air Fryer', 'tier' => 'hadiah_besar', 'is_capped' => true, 'active' => true,
        ]);
        DB::table('quota_caps')->insert([
            'brand' => config('app.brand'), 'prize_name' => 'Air Fryer', 'cap_units' => $cap, 'used_units' => $used,
            'program_start' => $start, 'program_end' => $end, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $prize;
    }

    private function gameAdmin(): User
    {
        return User::firstOrCreate(['email' => 'game@test.local'], [
            'name' => 'Game', 'password' => 'rahasia-123',
            'role' => 'game_admin', 'brand_scope' => config('app.brand'),
        ]);
    }

    // --- Papan terikat ke pemiliknya ---

    public function test_papan_tanpa_cookie_pemilik_tidak_bisa_dimainkan(): void
    {
        $session = $this->start();
        $token = $session->session_token;

        $this->get("/play/{$token}")->assertRedirect(route('play.form'));
        $this->postJson("/play/{$token}/pick", ['slots' => [1, 2, 3]])->assertForbidden();
        $this->postJson("/play/{$token}/reveal")->assertForbidden();
        $this->postJson("/play/{$token}/take", ['slot' => 1])->assertForbidden();
        $this->assertSame(0, $session->fresh()->chance_used);
    }

    public function test_cookie_pelanggan_lain_ditolak(): void
    {
        $session = $this->start();
        $other = Customer::create(['phone' => '6289999999999', 'brand' => config('app.brand')]);
        CustomerPin::set($other, '8264');

        $this->withCredentials()
            ->withCookie(RememberedCustomer::COOKIE, RememberedCustomer::payload($other->refresh()))
            ->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertForbidden();
    }

    // --- Jeda hadiah ---

    public function test_jeda_membuat_papan_baru_hanya_poin(): void
    {
        $this->bigPrize(5, now()->toDateString(), now()->addMonth()->toDateString());
        GameControls::store(['paused' => true, 'big_prize_chance' => 100], config('app.brand'));

        $session = $this->start();

        $this->assertSame(['poin'], $session->prizes()->distinct()->pluck('tier')->all());
    }

    public function test_jeda_mengubah_kado_yang_sudah_ada_jadi_poin_saat_dibuka(): void
    {
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Pouch', 'tier' => 'merch', 'active' => true]);
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'merch');
        GameControls::store(['paused' => true, 'big_prize_chance' => 20], config('app.brand'));

        $revealed = $this->openRound($session);

        $this->assertSame(['poin', 'poin', 'poin'], array_column($revealed, 'tier'));
    }

    // --- Peluang dan jadwal hadiah utama ---

    public function test_peluang_nol_tidak_menaruh_hadiah_utama(): void
    {
        $this->bigPrize(5, now()->toDateString(), now()->addMonth()->toDateString());
        GameControls::store(['paused' => false, 'big_prize_chance' => 0], config('app.brand'));

        $session = $this->start();

        $this->assertSame(0, $session->prizes()->where('tier', 'hadiah_besar')->count());
    }

    public function test_peluang_seratus_menaruh_satu_hadiah_utama(): void
    {
        $this->bigPrize(5, now()->toDateString(), now()->addMonth()->toDateString());
        $this->alwaysPlaceBigPrize();

        $session = $this->start();

        $this->assertSame(1, $session->prizes()->where('tier', 'hadiah_besar')->count());
    }

    public function test_peluang_default_dua_puluh_persen(): void
    {
        $this->assertSame(['paused' => false, 'big_prize_chance' => 20], GameControls::current());
    }

    public function test_kuota_dibuka_bertahap_sepanjang_periode(): void
    {
        $cap = (object) ['cap_units' => 2, 'program_start' => '2026-10-01', 'program_end' => '2026-11-29'];

        $this->assertSame(0, BigPrizeQuota::unlocked($cap, new \DateTime('2026-09-30')));
        $this->assertSame(1, BigPrizeQuota::unlocked($cap, new \DateTime('2026-10-01')));
        $this->assertSame(1, BigPrizeQuota::unlocked($cap, new \DateTime('2026-10-30')));
        $this->assertSame(2, BigPrizeQuota::unlocked($cap, new \DateTime('2026-10-31')));
        $this->assertSame(2, BigPrizeQuota::unlocked($cap, new \DateTime('2026-11-29')));
        $this->assertSame(0, BigPrizeQuota::unlocked($cap, new \DateTime('2026-11-30')));
    }

    public function test_unit_yang_belum_terbuka_tidak_bisa_keluar(): void
    {
        // Unit pertama sudah keluar, unit kedua baru terbuka di paruh kedua periode.
        $this->bigPrize(2, now()->toDateString(), now()->addDays(59)->toDateString(), used: 1);
        $this->alwaysPlaceBigPrize();

        $session = $this->start();

        $this->assertSame(0, $session->prizes()->where('tier', 'hadiah_besar')->count());
    }

    public function test_hadiah_utama_di_luar_periode_tidak_keluar(): void
    {
        $this->bigPrize(5, now()->subMonths(2)->toDateString(), now()->subDay()->toDateString());
        $this->alwaysPlaceBigPrize();

        $session = $this->start();

        $this->assertSame(0, $session->prizes()->where('tier', 'hadiah_besar')->count());
    }

    // --- Stok bulanan voucher/merch ---

    public function test_stok_bulanan_habis_kado_jadi_poin(): void
    {
        $item = PrizeCatalog::create([
            'brand' => config('app.brand'), 'name' => 'Voucher Ongkir', 'tier' => 'voucher', 'active' => true, 'monthly_stock' => 1,
        ]);
        $first = $this->start('JX1', '08111111111');
        $this->forceTiers($first, [1, 2, 3], 'voucher');
        $this->openRound($first);
        $this->playAs($first)->postJson("/play/{$first->session_token}/take", ['slot' => 1])->assertOk();
        $this->assertSame(1, Claim::count());

        $second = $this->start('JX2', '08222222222');
        $this->forceTiers($second, [1, 2, 3], 'voucher');

        $this->assertSame(['poin', 'poin', 'poin'], array_column($this->openRound($second), 'tier'));
        $this->assertSame(1, (int) $item->fresh()->monthly_stock);
    }

    public function test_stok_kosong_berarti_tanpa_batas(): void
    {
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Voucher Ongkir', 'tier' => 'voucher', 'active' => true]);
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'voucher');

        $this->assertSame(['voucher', 'voucher', 'voucher'], array_column($this->openRound($session), 'tier'));
    }

    public function test_item_nonaktif_tidak_diundi(): void
    {
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Totebag', 'tier' => 'merch', 'active' => false]);
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'merch');

        $this->assertSame(['poin', 'poin', 'poin'], array_column($this->openRound($session), 'tier'));
    }

    // --- Kado poin ikut nominal order ---

    public function test_kado_poin_ikut_nominal_order(): void
    {
        $session = $this->start();
        $session->update(['order_amount' => 150000]);
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $revealed = $this->openRound($session);
        foreach ($revealed as $prize) {
            $this->assertGreaterThanOrEqual(143, $prize['points']);
            $this->assertLessThanOrEqual(150, $prize['points']);
        }

        $take = $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 2])->assertOk();
        $shown = collect($revealed)->firstWhere('slot', 2)['points'];

        $this->assertSame($shown, $take->json('points'));
        $this->assertSame($shown, (int) PointLot::sum('points'));
    }

    public function test_tanpa_nominal_order_kado_poin_memakai_poin_per_kado(): void
    {
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $this->assertSame([200, 200, 200], array_column($this->openRound($session), 'points'));
    }

    public function test_gate_mengisi_nominal_order_ke_sesi(): void
    {
        $this->app->instance(OrderGate::class, new class implements OrderGate
        {
            public function isDelivered(string $awb, string $phone): GateResult
            {
                return GateResult::delivered('Budi', 240000);
            }
        });

        $this->assertSame(240000, (int) $this->start()->order_amount);
    }

    public function test_soscom_nominal_produk_dikurangi_diskon_tanpa_ongkir(): void
    {
        Http::fake(['https://wms.test/*' => Http::response(['code' => 200, 'data' => [
            'status' => 'completed', 'awb' => 'C1MACRQU', 'amount' => 250000, 'discount_amount' => 10000,
            'shipping_fee' => 15000, 'cod_fee' => 7500,
            'customer_shipping' => ['phone' => '628123456789'], 'orders' => ['status' => 'completed'],
        ]])]);

        $result = (new MarkomApiOrderGate(new SoscomOrderStatusSource('https://wms.test', 'key')))
            ->isDelivered('C1MACRQU', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertSame(240000, $result->orderAmount);
    }

    // --- Panel Game Admin ---

    public function test_admin_menyimpan_kendali_gatcha(): void
    {
        $this->actingAs($this->gameAdmin())
            ->post('/admin/game/controls', ['paused' => '1', 'big_prize_chance' => 10])
            ->assertRedirect();

        $this->assertSame(['paused' => true, 'big_prize_chance' => 10], GameControls::current());

        $this->actingAs($this->gameAdmin())
            ->post('/admin/game/controls', ['big_prize_chance' => 101])
            ->assertSessionHasErrors('big_prize_chance');
    }

    public function test_admin_mengubah_stok_dan_menonaktifkan_hadiah(): void
    {
        $item = PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Pouch', 'tier' => 'merch', 'active' => true]);

        $this->actingAs($this->gameAdmin())
            ->post("/admin/game/catalog/{$item->id}", ['monthly_stock' => 30, 'face_value' => 6800])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(30, (int) $item->monthly_stock);
        $this->assertFalse($item->active);
    }

    public function test_admin_mengubah_kuota_dan_periode_hadiah_utama(): void
    {
        $prize = $this->bigPrize(2, '2026-10-01', '2026-11-30', used: 1);

        $this->actingAs($this->gameAdmin())->post("/admin/game/catalog/{$prize->id}", [
            'active' => '1', 'cap_units' => 0, 'program_start' => '2026-10-01', 'program_end' => '2026-12-31',
        ])->assertSessionHasErrors('cap_units');

        $this->actingAs($this->gameAdmin())->post("/admin/game/catalog/{$prize->id}", [
            'active' => '1', 'cap_units' => 3, 'program_start' => '2026-10-01', 'program_end' => '2026-12-31',
        ])->assertRedirect();

        $cap = DB::table('quota_caps')->where('prize_name', 'Air Fryer')->first();
        $this->assertSame(3, (int) $cap->cap_units);
        $this->assertSame('2026-12-31', substr((string) $cap->program_end, 0, 10));
    }

    public function test_admin_brand_lain_tidak_bisa_mengubah_hadiah(): void
    {
        $item = PrizeCatalog::create(['brand' => 'brand-lain', 'name' => 'Pouch', 'tier' => 'merch', 'active' => true]);

        $this->actingAs($this->gameAdmin())
            ->post("/admin/game/catalog/{$item->id}", ['monthly_stock' => 1])
            ->assertNotFound();
    }

    public function test_panel_game_menampilkan_kendali(): void
    {
        $this->bigPrize(2, now()->toDateString(), now()->addDays(59)->toDateString());

        $this->actingAs($this->gameAdmin())->get('/admin/game')
            ->assertOk()
            ->assertSee('Kendali Gatcha')
            ->assertSee('Boleh keluar sampai hari ini: 1');
    }

    // --- Batas percobaan ---

    public function test_login_admin_dibatasi(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'x@test.local', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'x@test.local', 'password' => 'salah'])->assertStatus(429);
    }

    public function test_mulai_main_dibatasi_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/play/start', ['awb' => "JX{$i}", 'phone' => '0812345678'.($i % 10)]);
        }

        $this->post('/play/start', ['awb' => 'JX99', 'phone' => '08123456789'])->assertStatus(429);
    }

    // --- Katalog bulan pertama ---

    public function test_katalog_awal_terbit_dengan_item_terpilih(): void
    {
        $this->artisan('loyalty:katalog-awal', ['--period' => '2026-10'])->assertSuccessful();

        $catalog = RewardCatalog::where('period', '2026-10')->firstOrFail();
        $this->assertNotNull($catalog->published_at);
        $this->assertSame(6, $catalog->items()->where('active', true)->count());
        foreach (LaunchCatalog::items(config('app.brand')) as $item) {
            $this->assertSame($item['active'], (bool) $catalog->items()->where('name', $item['name'])->value('active'), $item['name']);
        }
    }

    public function test_katalog_awal_bisa_diulang_dan_menyembunyikan_item_lain(): void
    {
        $catalog = RewardCatalog::create(['brand' => config('app.brand'), 'period' => '2026-10']);
        $catalog->items()->create(['name' => 'Item lama', 'points_cost' => 900, 'active' => true, 'sort' => 1]);

        $this->artisan('loyalty:katalog-awal', ['--period' => '2026-10'])->assertSuccessful();
        $this->artisan('loyalty:katalog-awal', ['--period' => '2026-10'])->assertSuccessful();

        $this->assertSame(1, $catalog->items()->where('name', 'Pouch Exclusive')->count());
        $this->assertFalse((bool) $catalog->items()->where('name', 'Item lama')->value('active'));
    }

    public function test_katalog_awal_menolak_format_bulan_salah(): void
    {
        $this->artisan('loyalty:katalog-awal', ['--period' => '2026-13'])->assertFailed();
    }
}
