<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\PrizeCatalog;
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
        DB::table('quota_caps')->insert([
            'brand' => 'amura',
            'prize_name' => 'Emas 1 gram',
            'cap_units' => 1,
            'used_units' => 0,
            'program_start' => now(),
            'program_end' => now()->addMonth(),
        ]);

        $service = app(SessionService::class);
        $a = $service->open('JX1', '081100000001');
        $b = $service->open('JX2', '081100000002');

        $this->assertSame(1, $a->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertSame(0, $b->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertSame(1, (int) DB::table('quota_caps')->where('prize_name', 'Emas 1 gram')->value('used_units'));
    }
}
