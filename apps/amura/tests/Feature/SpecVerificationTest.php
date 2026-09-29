<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\DrawEngine;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Gate\StubOrderGate;
use LoyaltyNew\Core\Models\BrandScope;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\TierConfig;
use Tests\TestCase;

/**
 * Verifikasi end-to-end 8 cek spec §9.
 * Setiap test memetakan satu baris tabel §9 (lihat task-13-report.md).
 */
final class SpecVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ── Cek #1: Gate baca status_fulfillment='complete' ─────────────────────
    //
    // TERVERIFIKASI dengan MarkomApiOrderGate + sumber FairTrack SQLite:
    // order complete+HP cocok lolos; sent+problem / HP salah / tak ada ditolak.
    // (Test terpisah: GateIntegrationTest, MarkomGateTest.) Di sini dibuktikan
    // seam tetap OrderGate dan memakai gate nyata saat MARKOM_GATE=markom.
    public function test_01_gate_kontrak_dan_gate_nyata(): void
    {
        // Default (dev): stub, supaya alur jalan tanpa konfigurasi.
        $this->assertInstanceOf(StubOrderGate::class, app(OrderGate::class));

        // Gate nyata: sumber SQLite sementara.
        $db = tempnam(sys_get_temp_dir(), 'ft').'.db';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('JX-01','628123456789','sent','complete')");
        $pdo->exec("insert into orders values ('JX-01B','628123456789','sent','problem')");

        config([
            'markom.gate' => 'markom',
            'markom.driver' => 'fairtrack_sqlite',
            'markom.fairtrack.database' => $db,
        ]);
        app()->forgetInstance(OrderGate::class);

        $gate = app(OrderGate::class);
        $this->assertInstanceOf(OrderGate::class, $gate);
        $this->assertTrue($gate->isDelivered('JX-01', '08123456789')->delivered);        // complete → lolos
        $this->assertFalse($gate->isDelivered('JX-01B', '08123456789')->delivered);      // problem → tolak
        $this->assertFalse($gate->isDelivered('JX-01', '08999999999')->delivered);       // HP salah → tolak
        $this->assertFalse($gate->isDelivered('TIDAK-ADA', '08123456789')->delivered);   // tak ada → tolak

        @unlink($db);
    }

    // ── Cek #2: 2 chance dikunci di DB ──────────────────────────────────────
    public function test_02_chance_dikunci_db(): void
    {
        $service = app(SessionService::class);
        $session = $service->open('JX-02', '081200000002');

        $this->assertTrue($service->consumeChance($session));
        $this->assertTrue($service->consumeChance($session->fresh()));
        $this->assertFalse($service->consumeChance($session->fresh())); // ke-3 ditolak

        // Refresh / re-fetch token tidak menambah chance: tetap 2 terpakai.
        $this->assertSame(2, $session->fresh()->chance_used);
        $this->assertSame(2, GameSession::find($session->id)->chance_used);

        // Membuka ulang sesi (token ulang) mengembalikan baris yang sama.
        $this->assertSame($session->id, $service->open('JX-02', '081200000002')->id);
        $this->assertSame(2, $session->fresh()->chance_used);
    }

    // ── Cek #3: Bobot per sesi (1000 sesi ≈ T1 65 / T2 32 / T3 3) ───────────
    public function test_03_bobot_per_sesi(): void
    {
        $expected = TierConfig::current();
        $this->assertSame(TierConfig::default(), $expected);

        $engine = new DrawEngine($expected);
        $counts = ['poin' => 0, 'voucher' => 0, 'merch' => 0];

        foreach (range(1, 1000) as $seed) {
            $counts[$engine->pick($seed)]++;
        }

        $total = array_sum($counts);
        foreach (['poin' => 65, 'voucher' => 32, 'merch' => 3] as $tier => $weight) {
            $observed = $counts[$tier] / $total * 100;
            $this->assertEqualsWithDelta(
                $weight,
                $observed,
                5.0,
                "Sebaran {$tier} = {$observed}% di luar toleransi 5pp dari {$weight}%",
            );
        }

        // Bobot yang dipakai sesi tersimpan per sesi, tidak dikirim ke browser.
        $session = app(SessionService::class)->open('JX-03', '081200000003');
        $this->assertSame($expected, $session->tier_config);
    }

    // ── Cek #4: Cap hadiah besar (Emas ke-2 dalam 1 program ditolak) ────────
    public function test_04_cap_hadiah_besar(): void
    {
        PrizeCatalog::create([
            'brand' => 'amura',
            'name' => 'Emas 1 gram',
            'tier' => 'hadiah_besar',
            'is_capped' => true,
            'active' => true,
        ]);
        DB::table('quota_caps')->insert([
            'brand' => 'amura',
            'prize_name' => 'Emas 1 gram',
            'cap_units' => 1,
            'used_units' => 0,
            'program_start' => now(),
            'program_end' => now()->addMonth(),
        ]);

        $service = app(SessionService::class);
        $first = $service->open('JX-04A', '081200000041');
        $second = $service->open('JX-04B', '081200000042');

        // Kuota dipotong saat hadiah besar terbuka, bukan saat sesi dibuat.
        $this->assertSame(1, $first->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertSame(1, $second->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertSame(0, (int) DB::table('quota_caps')->where('prize_name', 'Emas 1 gram')->value('used_units'));

        $this->assertContains('hadiah_besar', array_column($this->revealBigPrizeRound($first), 'tier'));
        $this->assertSame(1, (int) DB::table('quota_caps')->where('prize_name', 'Emas 1 gram')->value('used_units'));

        // Emas ke-2: kado diundi ulang ke tier biasa sebelum tampil.
        $this->assertNotContains('hadiah_besar', array_column($this->revealBigPrizeRound($second), 'tier'));
        $this->assertSame(0, $second->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertSame(1, (int) DB::table('quota_caps')->where('prize_name', 'Emas 1 gram')->value('used_units'));

        // Kuota habis: sesi baru tidak mendapat hadiah besar sama sekali.
        $third = $service->open('JX-04C', '081200000043');
        $this->assertSame(0, $third->prizes()->where('tier', 'hadiah_besar')->count());
    }

    /** Pilih slot hadiah besar + 2 slot lain lalu buka; kembalikan isi yang terbuka. */
    private function revealBigPrizeRound(GameSession $session): array
    {
        $big = $session->prizes()->where('tier', 'hadiah_besar')->value('slot');
        $others = $session->prizes()->where('slot', '!=', $big)->orderBy('slot')->limit(2)->pluck('slot')->all();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [$big, ...$others]])->assertOk();

        return $this->postJson("/play/{$session->session_token}/reveal")->assertOk()->json('revealed');
    }

    // ── Cek #5: AWB+HP harus cocok (anti-fraud) ─────────────────────────────
    public function test_05_awb_hp_harus_cocok(): void
    {
        // Cocok walau format HP beda (+62 / tanda hubung dinormalisasi).
        $this->assertTrue(AwbMatcher::matches('JX-05', '08123456789', 'JX-05', '+62 812-3456-789'));

        // AWB valid + HP salah → ditolak (inti anti-fraud).
        $this->assertFalse(AwbMatcher::matches('JX-05', '08999999999', 'JX-05', '08123456789'));

        // AWB salah + HP benar → ditolak.
        $this->assertFalse(AwbMatcher::matches('JX-XX', '08123456789', 'JX-05', '08123456789'));

        // Fail closed: sisi mana pun kosong tidak pernah cocok.
        $this->assertFalse(AwbMatcher::matches('', '08123456789', 'JX-05', '08123456789'));
        $this->assertFalse(AwbMatcher::matches('JX-05', '', 'JX-05', '08123456789'));

        $this->assertSame('08123456789', AwbMatcher::normalizePhone('+62 812-3456-789'));
    }

    // ── Cek #6: Batas klaim 14 / 30 hari + auto-hangus ──────────────────────
    //
    // Expiry dihitung saat klaim dibuat, DAN job auto-hangus (loyalty:expire-claims)
    // menandai klaim lewat batas sebagai 'hangus'. Keduanya diverifikasi di sini.
    public function test_06_batas_klaim_dan_auto_hangus(): void
    {
        $this->assertSame(14, ClaimService::expiryFor('poin'));
        $this->assertSame(14, ClaimService::expiryFor('voucher'));
        $this->assertSame(14, ClaimService::expiryFor('merch'));
        $this->assertSame(30, ClaimService::expiryFor('hadiah_besar'));

        $prizeBiasa = $this->prize('poin', 'JX-06A');
        $prizeUtama = $this->prize('hadiah_besar', 'JX-06B');

        $biasa = ClaimService::create($prizeBiasa->session, $prizeBiasa);
        $utama = ClaimService::create($prizeUtama->session, $prizeUtama);

        $this->assertEqualsWithDelta(14, now()->diffInDays($biasa->expires_at), 0.1);
        $this->assertEqualsWithDelta(30, now()->diffInDays($utama->expires_at), 0.1);

        // Auto-hangus: yang lewat batas di-hangus, yang belum tidak.
        $biasa->forceFill(['expires_at' => now()->subDay()])->save();
        app('db')->table('claims')->where('id', $utama->id)
            ->update(['expires_at' => now()->addDays(30)]);

        $this->artisan('loyalty:expire-claims')->assertExitCode(0);

        $this->assertSame('hangus', $biasa->fresh()->status);
        $this->assertSame('belum_redeem', $utama->fresh()->status);
    }

    // ── Cek #7: Isolasi brand (Amura tidak bisa baca data Reglow) ───────────
    public function test_07_isolasi_brand(): void
    {
        Customer::withoutGlobalScope(BrandScope::class)->create(['phone' => '08111', 'brand' => 'reglow']);
        Customer::create(['phone' => '08222', 'brand' => 'amura']);

        $visible = Customer::all();
        $this->assertCount(1, $visible);
        $this->assertSame('amura', $visible->first()->brand);

        // Baris reglow benar-benar ada di DB — bukti filter ada di scope.
        $this->assertSame(1, Customer::withoutGlobalScope(BrandScope::class)
            ->where('brand', 'reglow')->count());
    }

    // Cek #8 ("suite hijau") bukan test — ia adalah eksekusi suite penuh oleh
    // controller (lihat laporan). Tidak ada test self-referential palsu.

    private function makeSession(string $awb = 'JX-06A'): GameSession
    {
        return GameSession::create([
            'customer_id' => Customer::create([
                'phone' => '08'.random_int(100000000, 999999999),
                'brand' => 'amura',
            ])->id,
            'brand' => 'amura',
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => TierConfig::current(),
            'awb' => $awb,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function prize(string $tier, string $awb = 'JX-06A'): GamePrize
    {
        $session = $this->makeSession($awb);
        $prize = GamePrize::create([
            'game_session_id' => $session->id,
            'slot' => 1,
            'tier' => $tier,
        ]);
        $prize->setRelation('session', $session);

        return $prize;
    }
}
