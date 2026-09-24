<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\BrandScope;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

final class BrandIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** Bikin customer mentah (tanpa BrandScope) untuk brand tertentu. */
    private function rawCustomer(string $brand, string $phone): Customer
    {
        return Customer::withoutGlobalScope(BrandScope::class)->create([
            'phone' => $phone,
            'brand' => $brand,
        ]);
    }

    /** Bikin sesi mentah (tanpa BrandScope) untuk brand tertentu. */
    private function rawSession(string $brand, string $awb): GameSession
    {
        $customer = $this->rawCustomer($brand, '08'.random_int(100000000, 999999999));

        return GameSession::withoutGlobalScope(BrandScope::class)->create([
            'customer_id' => $customer->id,
            'brand' => $brand,
            'session_token' => bin2hex(random_bytes(8)),
            'chance_total' => 2,
            'chance_used' => 0,
            'tier_config' => [],
            'awb' => $awb,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_app_amura_tidak_melihat_data_reglow(): void
    {
        // Review Focus: kebocoran lintas brand
        Customer::withoutGlobalScopes()->create(['phone' => '08111', 'brand' => 'reglow']);
        Customer::create(['phone' => '08222', 'brand' => 'amura']);

        $visible = Customer::all();
        $this->assertCount(1, $visible);
        $this->assertSame('amura', $visible->first()->brand);
    }

    public function test_sesi_tidak_bocor_lintas_brand(): void
    {
        $this->rawSession('reglow', 'JX-REGLOW');
        $this->rawSession('amura', 'JX-AMURA');

        $visible = GameSession::all();
        $this->assertCount(1, $visible);
        $this->assertSame('amura', $visible->first()->brand);
        $this->assertSame(1, GameSession::count());
    }

    public function test_without_global_scope_bisa_akses_lintas_brand(): void
    {
        $this->rawCustomer('reglow', '08111');
        $this->rawCustomer('amura', '08222');

        // Bukti bahwa filter ada di scope, bukan di DB: tanpa scope kedua brand terlihat.
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Customer::withoutGlobalScope(BrandScope::class)->count());
    }

    public function test_relasi_sesi_ke_customer_menghormati_brand(): void
    {
        $customer = $this->rawCustomer('amura', '08123');
        $session = GameSession::withoutGlobalScope(BrandScope::class)->create([
            'customer_id' => $customer->id,
            'brand' => 'amura',
            'session_token' => bin2hex(random_bytes(8)),
            'chance_total' => 2,
            'chance_used' => 0,
            'tier_config' => [],
            'awb' => 'JX-AMURA',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame($customer->id, $session->customer?->id);
        $this->assertSame('amura', $session->customer?->brand);

        // Sesi reglow (dibuat mentah) tidak bisa menembus ke customer-nya
        // saat dibaca dari konteks amura: relasi ikut ter-scope BrandScope.
        $reglowSession = $this->rawSession('reglow', 'JX-REGLOW');
        $this->assertNull($reglowSession->customer);
    }

    public function test_scope_memfilter_baca_bukan_menolak_tulis_brand_lain(): void
    {
        // BrandScope berbasis `where`: menulis baris brand lain TETAP bisa
        // (tidak error), tetapi baris itu tidak pernah terbaca dari app ini.
        $this->rawCustomer('reglow', '08999');

        $this->assertSame(0, Customer::where('brand', 'reglow')->count());
        $this->assertSame(1, Customer::withoutGlobalScope(BrandScope::class)->count());
    }
}
