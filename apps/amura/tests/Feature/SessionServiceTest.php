<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\SessionExpiredException;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Gate\GateResult;
use LoyaltyNew\Core\Gate\OrderGate;
use Tests\TestCase;

final class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buat_sesi_menghasilkan_24_kado(): void
    {
        $session = app(SessionService::class)->open('JX123', '08123456789');
        $this->assertSame(24, $session->prizes()->count());
        $this->assertSame(2, $session->chance_total);
        $this->assertSame(0, $session->chance_used);
    }

    public function test_chance_dikunci_di_db(): void
    {
        // Review Focus: token ulang / refresh tidak boleh menambah chance
        $service = app(SessionService::class);
        $session = $service->open('JX123', '08123456789');

        $this->assertTrue($service->consumeChance($session));
        $this->assertTrue($service->consumeChance($session->fresh()));
        $this->assertFalse($service->consumeChance($session->fresh())); // chance habis

        $this->assertSame(2, $session->fresh()->chance_used);
    }

    public function test_awb_sama_hp_sama_reuse_sesi(): void
    {
        $service = app(SessionService::class);
        $a = $service->open('JX123', '08123456789');
        $b = $service->open('JX123', '08123456789');
        $this->assertSame($a->id, $b->id);
    }

    public function test_hadiah_besar_dibatasi_cap(): void
    {
        // Review Focus: kuota Emas 1 -> sesi kedua tidak boleh dapat Emas lagi.
        PrizeCatalog::create([
            'brand' => 'amura',
            'name' => 'Emas 1 gram',
            'tier' => 'hadiah_besar',
            'is_capped' => true,
            'active' => true,
        ]);
        $this->alwaysPlaceBigPrize();
        DB::table('quota_caps')->insert([
            'brand' => 'amura',
            'prize_name' => 'Emas 1 gram',
            'cap_units' => 1,
            'used_units' => 0,
            'program_start' => now(),
            'release_from' => now(),
            'program_end' => now()->addMonth(),
        ]);

        $service = app(SessionService::class);
        $this->previousOrder('081200000041');
        $this->previousOrder('081200000042');
        $first = $service->open('JX1', '081200000041');
        $second = $service->open('JX2', '081200000042');

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
        $third = $service->open('JX3', '081200000043');
        $this->assertSame(0, $third->prizes()->where('tier', 'hadiah_besar')->count());
    }

    /** Pilih slot hadiah besar + 2 slot lain lalu buka; kembalikan isi yang terbuka. */
    private function revealBigPrizeRound(GameSession $session): array
    {
        $big = $session->prizes()->where('tier', 'hadiah_besar')->value('slot');
        $others = $session->prizes()->where('slot', '!=', $big)->orderBy('slot')->limit(2)->pluck('slot')->all();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [$big, ...$others]])->assertOk();

        return $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk()->json('revealed');
    }

    public function test_unique_brand_phone_ditolak(): void
    {
        DB::table('customers')->insert([
            'brand' => 'amura',
            'phone' => '08123456789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('customers')->insert([
            'brand' => 'amura',
            'phone' => '08123456789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_open_dua_kali_awb_hp_sama_hasil_satu_baris(): void
    {
        $service = app(SessionService::class);
        $service->open('JX123', '08123456789');
        $service->open('JX123', '08123456789');

        $this->assertDatabaseCount('game_sessions', 1);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_open_mengembalikan_sesi_yang_sudah_ada_tanpa_duplikat(): void
    {
        $service = app(SessionService::class);
        $first = $service->open('JX123', '08123456789');

        $customer = Customer::firstOrCreate(['phone' => '08123456789', 'brand' => 'amura']);
        $this->assertSame($customer->id, $first->customer_id);

        $second = $service->open('JX123', '08123456789');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('game_sessions', 1);
        $this->assertSame(1, GameSession::where('customer_id', $customer->id)->where('awb', 'JX123')->count());
    }

    public function test_open_sesi_kedaluwarsa_ditolak_tanpa_sesi_baru(): void
    {
        $first = app(SessionService::class)->open('JX123', '08123456789');
        $first->update(['expires_at' => now()->subDay()]);

        try {
            app(SessionService::class)->open('JX123', '08123456789');
            $this->fail('Sesi kedaluwarsa seharusnya ditolak');
        } catch (SessionExpiredException) {
        }

        $this->assertDatabaseCount('game_sessions', 1);
    }

    public function test_open_menyimpan_nama_pelanggan_dari_gate(): void
    {
        $gate = new class implements OrderGate {
            public function isDelivered(string $awb, string $phone): GateResult
            {
                return GateResult::delivered('Sari Wulandari');
            }
        };

        $service = new SessionService($gate, (string) config('app.brand'));
        $session = $service->open('JX-NAMA', '081234560000');

        $this->assertSame('Sari Wulandari', Customer::findOrFail($session->customer_id)->name);
    }
}
