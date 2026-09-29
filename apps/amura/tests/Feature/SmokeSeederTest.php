<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use LoyaltyNew\Core\Seeding\SmokeSeeder;
use Tests\TestCase;

/**
 * Smoke seeder harus menghasilkan data yang membuat SELURUH alur bisa dites,
 * dan harus idempotent.
 */
class SmokeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedSmoke(): void
    {
        config(['app.brand' => 'amura']);
        (new SmokeSeeder)->run();
    }

    public function test_membuat_empat_peran_dan_katalog_lengkap(): void
    {
        $this->seedSmoke();

        foreach (['cs', 'game_admin', 'finance', 'super_admin'] as $role) {
            $this->assertDatabaseHas('users', ['role' => $role, 'brand_scope' => 'amura']);
        }

        foreach (['poin', 'voucher', 'merch', 'hadiah_besar'] as $tier) {
            $this->assertTrue(
                PrizeCatalog::withoutGlobalScopes()->where('brand', 'amura')->where('tier', $tier)->exists(),
                "tier {$tier} harus ada di katalog",
            );
        }

        $this->assertDatabaseHas('quota_caps', ['brand' => 'amura', 'prize_name' => 'Emas 0,5 gram', 'cap_units' => 1]);
        $this->assertDatabaseHas('quota_caps', ['brand' => 'amura', 'prize_name' => 'Philips Air Fryer', 'cap_units' => 2]);
    }

    public function test_klaim_contoh_untuk_antrian_cs_dan_finance(): void
    {
        $this->seedSmoke();

        $std = Claim::where('claim_code', 'SMOKE-amura-STD')->firstOrFail();
        $this->assertSame('diajukan', $std->status);
        $this->assertSame('voucher', $std->gamePrize->tier);
        $this->assertSame('amura', $std->brand);
        $this->assertNotNull($std->prize_name);
        $this->assertSame(2, $std->events()->count());

        $big = Claim::where('claim_code', 'SMOKE-amura-BIG')->firstOrFail();
        $this->assertSame('perlu_finance', $big->status);
        $this->assertSame('hadiah_besar', $big->gamePrize->tier);
    }

    public function test_katalog_bulan_ini_dan_saldo_demo(): void
    {
        $this->seedSmoke();

        $catalog = RewardCatalogService::active('amura');
        $this->assertNotNull($catalog);
        $this->assertSame(count(SmokeSeeder::POINT_CATALOG['amura']), $catalog->items()->count());

        $this->assertSame(1200, (new PointWallet($this->demoCustomer()))->balance());
    }

    /** Pelanggan SMOKE-CLAIM dicari lewat AWB supaya test tetap benar setelah brand ditukar untuk Reglow. */
    private function demoCustomer(): Customer
    {
        $customerId = GameSession::withoutGlobalScopes()->where('awb', 'SMOKE-CLAIM-AMURA')->value('customer_id');

        return Customer::withoutGlobalScopes()->findOrFail($customerId);
    }

    public function test_awb_dummy_lolos_gate_dan_bisa_mulai_sesi(): void
    {
        $this->seedSmoke();

        $res = $this->post(route('play.start'), [
            'awb' => 'SMOKE-AMURA-001',
            'phone' => '6281100000001',
        ]);

        $res->assertRedirect();
        $this->assertTrue(GameSession::withoutGlobalScopes()->where('awb', 'SMOKE-AMURA-001')->exists());
    }

    public function test_idempotent_dijalankan_dua_kali(): void
    {
        $this->seedSmoke();
        $this->seedSmoke();

        $this->assertSame(4, Claim::where('claim_code', 'like', 'SMOKE-%')->count());
        $this->assertSame(1, PrizeCatalog::withoutGlobalScopes()
            ->where('brand', 'amura')->where('name', 'Emas 0,5 gram')->count());
        $this->assertSame(1200, (new PointWallet($this->demoCustomer()))->balance());
        $this->assertSame(count(SmokeSeeder::POINT_CATALOG['amura']), RewardCatalogService::active('amura')->items()->count());
    }
}
