<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\TierConfig;
use LoyaltyNew\Core\Seeding\SmokeSeeder;
use Tests\TestCase;

/**
 * Regresi untuk bug yang ditemukan saat uji jalan 2026-09-28
 * (docs/bugfix-report-2026-09-28.md). Nomor test = nomor bug di laporan.
 */
final class BugfixRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function start(string $awb = 'JX123', string $phone = '08123456789'): GameSession
    {
        $this->post('/play/start', ['awb' => $awb, 'phone' => $phone])->assertRedirect();

        return GameSession::latest('id')->firstOrFail();
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => $role,
            'email' => $role.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $this->brand(),
        ]);
    }

    private function bigPrizeCatalog(int $cap = 1): void
    {
        PrizeCatalog::create([
            'brand' => $this->brand(),
            'name' => 'Emas 1 gram',
            'tier' => 'hadiah_besar',
            'is_capped' => true,
            'active' => true,
        ]);
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(),
            'prize_name' => 'Emas 1 gram',
            'cap_units' => $cap,
            'used_units' => 0,
            'program_start' => now(),
            'program_end' => now()->addMonth(),
        ]);
    }

    private function usedUnits(): int
    {
        return (int) DB::table('quota_caps')->where('prize_name', 'Emas 1 gram')->value('used_units');
    }

    // ── Bug 1: AWB beda huruf besar/kecil membuat sesi kedua ────────────────
    public function test_1_awb_beda_huruf_dan_spasi_tetap_satu_sesi(): void
    {
        $first = $this->start('jx-777', '08123456789');
        $second = $this->start('  JX-777 ', '08123456789');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, GameSession::count());
        $this->assertSame('JX-777', $first->awb);
    }

    // ── Bug 2: kuota hadiah besar dipotong saat terbuka, bukan saat sesi dibuat
    public function test_2_membuat_sesi_tidak_memotong_kuota(): void
    {
        $this->bigPrizeCatalog(cap: 1);

        foreach (range(1, 3) as $i) {
            $this->start("JX-Q{$i}", "0812000000{$i}");
        }

        $this->assertSame(0, $this->usedUnits());
    }

    /** Pilih slot hadiah besar + 2 slot lain. @return array{0:int, 1:array<int,int>} */
    private function pickBigPrizeRound(GameSession $session): array
    {
        $big = $session->prizes()->where('tier', 'hadiah_besar')->value('slot');
        $others = $session->prizes()->where('slot', '!=', $big)->orderBy('slot')->limit(2)->pluck('slot')->all();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [$big, ...$others]])->assertOk();

        return [$big, $others];
    }

    public function test_2_reveal_hadiah_utama_langsung_masuk_keranjang(): void
    {
        $this->bigPrizeCatalog();
        $session = $this->start();
        [$big, $others] = $this->pickBigPrizeRound($session);

        $this->postJson("/play/{$session->session_token}/reveal")
            ->assertOk()
            ->assertJson(['auto_taken' => true, 'chosen' => $big, 'chance_left' => 1, 'chance_total' => 2]);

        $this->assertSame(1, $this->usedUnits());
        $this->assertSame(1, $session->fresh()->chance_used);
        $this->assertSame('belum_redeem', Claim::firstOrFail()->status);
        $this->assertTrue((bool) $session->prizes()->where('slot', $big)->value('taken'));
        // Dua kado lain otomatis tidak terpilih: ronde selesai, tidak bisa diambil lagi.
        $this->assertSame(0, $session->prizes()->whereIn('slot', $others)->where('picked', true)->count());
        $this->postJson("/play/{$session->session_token}/take", ['slot' => $others[0]])->assertStatus(422);
        $this->assertSame(1, Claim::count());
    }

    public function test_2_take_tanpa_reveal_hadiah_utama_tetap_yang_diambil(): void
    {
        $this->bigPrizeCatalog();
        $session = $this->start();
        [$big, $others] = $this->pickBigPrizeRound($session);

        $this->postJson("/play/{$session->session_token}/take", ['slot' => $others[0]])
            ->assertOk()
            ->assertJson(['chosen' => $big]);

        $this->assertSame(1, $this->usedUnits());
        $this->assertSame('belum_redeem', Claim::firstOrFail()->status);
    }

    public function test_2_reveal_dua_kali_tidak_memotong_kuota_dua_kali(): void
    {
        $this->bigPrizeCatalog(cap: 2);
        $session = $this->start();
        $this->pickBigPrizeRound($session);

        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();
        $this->postJson("/play/{$session->session_token}/reveal")->assertStatus(422);

        $this->assertSame(1, $this->usedUnits());
        $this->assertSame(1, Claim::count());
    }

    public function test_2_klaim_hadiah_utama_hangus_otomatis_kuota_kembali(): void
    {
        $this->bigPrizeCatalog(cap: 1);
        $session = $this->start();
        $this->pickBigPrizeRound($session);
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();
        $this->assertSame(1, $this->usedUnits());

        // Kuota habis: sesi baru tidak mendapat hadiah utama.
        $this->assertSame(0, $this->start('JX-NEXT1', '081200000091')->prizes()->where('tier', 'hadiah_besar')->count());

        Claim::firstOrFail()->update(['expires_at' => now()->subDay()]);
        $this->assertSame(1, ClaimService::expireOverdue());
        $this->assertSame(0, $this->usedUnits());

        // Dijalankan lagi tidak mengembalikan kuota dua kali.
        $this->assertSame(0, ClaimService::expireOverdue());
        $this->assertSame(0, $this->usedUnits());

        // Emas boleh muncul lagi untuk pelanggan berikutnya.
        $this->assertSame(1, $this->start('JX-NEXT2', '081200000092')->prizes()->where('tier', 'hadiah_besar')->count());
    }

    public function test_2_cs_menghanguskan_klaim_hadiah_utama_kuota_kembali(): void
    {
        $this->bigPrizeCatalog(cap: 1);
        $session = $this->start();
        $this->pickBigPrizeRound($session);
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();
        $claim = Claim::firstOrFail();
        $cs = $this->staff('cs');

        $this->actingAs($cs)
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'hangus'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $this->usedUnits());

        // Klik kedua ditolak (sudah final) dan tidak mengurangi kuota lagi.
        $this->actingAs($cs)
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'hangus'])
            ->assertSessionHasErrors('status');
        $this->assertSame(0, $this->usedUnits());
    }

    public function test_2_klaim_hadiah_biasa_hangus_tidak_menyentuh_kuota(): void
    {
        $this->bigPrizeCatalog(cap: 2);
        DB::table('quota_caps')->update(['used_units' => 1]);
        $claim = $this->claim('belum_redeem', expired: true);

        ClaimService::expireOverdue();

        $this->assertSame('hangus', $claim->fresh()->status);
        $this->assertSame(1, $this->usedUnits());
    }

    // ── Bug 3: sesi kedaluwarsa masih bisa dimainkan ────────────────────────
    public function test_3_sesi_kedaluwarsa_tidak_bisa_dimainkan(): void
    {
        $session = $this->start();
        $session->update(['expires_at' => now()->subDay()]);
        $token = $session->session_token;

        $this->get("/play/{$token}")->assertRedirect(route('play.form'));
        $this->postJson("/play/{$token}/pick", ['slots' => [1, 2, 3]])->assertStatus(422);
        $this->postJson("/play/{$token}/reveal")->assertStatus(422);
        $this->postJson("/play/{$token}/take", ['slot' => 1])->assertStatus(422);

        $this->assertSame(0, Claim::count());
        $this->assertSame(0, $session->fresh()->chance_used);
    }

    public function test_3_ronde_tertunda_di_sesi_kedaluwarsa_tidak_bisa_diambil(): void
    {
        $session = $this->start();
        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $session->update(['expires_at' => now()->subDay()]);

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertStatus(422);
        $this->assertSame(0, Claim::count());
    }

    public function test_3_masuk_ulang_sesi_kedaluwarsa_ditolak_tanpa_menambah_lockout(): void
    {
        $session = $this->start();
        $session->update(['expires_at' => now()->subDay()]);

        $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789'])
            ->assertSessionHasErrors('awb');

        $this->assertSame(1, GameSession::count());
        $this->assertSame(0, DB::table('play_attempts')->count());
    }

    // ── Bug 4: CS bisa menghidupkan lagi klaim final / kedaluwarsa ──────────
    private function claim(string $status, bool $expired = false): Claim
    {
        $session = $this->start('JX-C'.random_int(1000, 9999), '0812'.random_int(1000000, 9999999));
        $prize = $session->prizes()->where('tier', '!=', 'hadiah_besar')->firstOrFail();
        $claim = ClaimService::create($session, $prize);
        $claim->update([
            'status' => $status,
            'expires_at' => $expired ? now()->subDay() : now()->addDays(14),
        ]);

        return $claim;
    }

    public function test_4_cs_tidak_bisa_mengubah_klaim_hangus(): void
    {
        $claim = $this->claim('hangus', expired: true);

        $this->actingAs($this->staff('cs'))
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'selesai'])
            ->assertSessionHasErrors('status');

        $this->assertSame('hangus', $claim->fresh()->status);
    }

    public function test_4_cs_tidak_bisa_mengubah_klaim_selesai(): void
    {
        $claim = $this->claim('selesai');

        $this->actingAs($this->staff('cs'))
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'hangus'])
            ->assertSessionHasErrors('status');

        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_4_klaim_lewat_batas_hanya_bisa_dihanguskan(): void
    {
        $claim = $this->claim('belum_redeem', expired: true);
        $cs = $this->staff('cs');

        $this->actingAs($cs)
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'selesai'])
            ->assertSessionHasErrors('status');
        $this->assertSame('belum_redeem', $claim->fresh()->status);

        $this->actingAs($cs)
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'hangus'])
            ->assertSessionHasNoErrors();
        $this->assertSame('hangus', $claim->fresh()->status);
    }

    public function test_4_finance_tidak_bisa_setujui_klaim_lewat_batas(): void
    {
        $claim = $this->claim('perlu_finance', expired: true);

        $this->actingAs($this->staff('finance'))
            ->post("/admin/finance/{$claim->claim_code}/approve")
            ->assertSessionHasErrors('status');

        $this->assertSame('perlu_finance', $claim->fresh()->status);
    }

    public function test_4_pesan_error_tampil_di_panel_admin(): void
    {
        $claim = $this->claim('hangus', expired: true);
        $cs = $this->staff('cs');

        $this->actingAs($cs)
            ->from('/admin/claims')
            ->followingRedirects()
            ->post("/admin/claims/{$claim->claim_code}/resolve", ['status' => 'selesai'])
            ->assertSee('tidak bisa diubah');
    }

    // ── Bug 5: refresh di tengah ronde membuat papan macet ──────────────────
    public function test_5_refresh_setelah_reveal_menampilkan_lagi_pilihan_hadiah(): void
    {
        $session = $this->start();
        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $response = $this->get("/play/{$session->session_token}")->assertOk();
        $this->assertSame([1, 2, 3], $response->viewData('pendingSlots'));
        $this->assertSame([1, 2, 3], array_column($response->viewData('pendingRevealed'), 'slot'));

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 2])->assertOk();
    }

    public function test_5_refresh_setelah_pick_sebelum_reveal_bisa_lanjut_buka(): void
    {
        $session = $this->start();
        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])->assertOk();

        $response = $this->get("/play/{$session->session_token}")->assertOk();
        $this->assertSame([4, 5, 6], $response->viewData('pendingSlots'));
        // Belum dibuka: isi kado tidak boleh ikut terkirim ke browser.
        $this->assertSame([], $response->viewData('pendingRevealed'));

        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();
    }

    // ── Bug 6: lockout mengunci lagi setelah 1x salah ───────────────────────
    public function test_6_setelah_kunci_habis_hitungan_mulai_dari_nol(): void
    {
        $limiter = AttemptLimiter::fromConfig($this->brand());
        foreach (range(1, 5) as $i) {
            $limiter->recordFailure('JX-L', '081299990000');
        }
        $this->assertNotNull($limiter->lockedFor('JX-L', '081299990000'));

        $this->travel(16)->minutes();
        $this->assertNull($limiter->lockedFor('JX-L', '081299990000'));

        $limiter->recordFailure('JX-L', '081299990000');
        $this->assertNull($limiter->lockedFor('JX-L', '081299990000'));
    }

    public function test_6_kegagalan_lama_tidak_ikut_dihitung(): void
    {
        $limiter = AttemptLimiter::fromConfig($this->brand());
        foreach (range(1, 4) as $i) {
            $limiter->recordFailure('JX-L', '081299990000');
        }

        $this->travel(1)->days();
        $limiter->recordFailure('JX-L', '081299990000');

        $this->assertNull($limiter->lockedFor('JX-L', '081299990000'));
    }

    // ── Bug 7: bobot tier 0/0/0 membuat /play/start error 500 ───────────────
    public function test_7_bobot_total_nol_ditolak_validasi(): void
    {
        $this->actingAs($this->staff('game_admin'))
            ->post('/admin/game/tiers', ['poin' => 0, 'voucher' => 0, 'merch' => 0])
            ->assertSessionHasErrors('poin');

        $this->assertSame(TierConfig::default(), TierConfig::current());
    }

    public function test_7_bobot_nol_yang_terlanjur_tersimpan_tidak_membuat_error(): void
    {
        TierConfig::store(['poin' => 0, 'voucher' => 0, 'merch' => 0]);

        $this->assertSame(TierConfig::default(), TierConfig::current());
        $this->start();
        $this->assertSame(1, GameSession::count());
    }

    // ── Tambahan: sesi smoke seed tidak tertahan di ronde setengah jalan ─────
    public function test_seeder_sesi_selesai_bisa_lanjut_ronde_dan_kuota_tercatat(): void
    {
        (new SmokeSeeder)->run();
        (new SmokeSeeder)->run();

        $session = GameSession::where('awb', 'SMOKE-CLAIM-'.strtoupper($this->brand()))->firstOrFail();
        $picked = $session->prizes()->where('picked', true)->where('taken', false)->count();
        $this->assertSame(0, $picked);

        $free = $session->prizes()->where('opened', false)->orderBy('slot')->limit(3)->pluck('slot')->all();
        $this->postJson("/play/{$session->session_token}/pick", ['slots' => $free])->assertOk();

        $used = (int) DB::table('quota_caps')
            ->where('brand', $this->brand())
            ->where('prize_name', 'Emas 0,5 gram')
            ->value('used_units');
        $this->assertSame(1, $used);
    }

    public function test_app_service_provider_memakai_brand_dari_config(): void
    {
        config(['app.brand' => 'brand-uji']);
        app()->forgetInstance(SessionService::class);

        $service = app(SessionService::class);
        $brand = (new \ReflectionProperty($service, 'brand'))->getValue($service);

        $this->assertSame('brand-uji', $brand);
    }
}
