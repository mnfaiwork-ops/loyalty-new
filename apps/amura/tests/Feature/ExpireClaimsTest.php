<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

/**
 * Auto-hangus klaim kedaluwarsa (spec §9 cek #6).
 */
class ExpireClaimsTest extends TestCase
{
    use RefreshDatabase;

    private function makeClaim(string $brand, string $status, string $expiresAt): Claim
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'phone' => '628'.random_int(100000000, 999999999),
            'brand' => $brand,
        ]);

        $session = GameSession::withoutGlobalScopes()->create([
            'customer_id' => $customer->id,
            'brand' => $brand,
            'session_token' => bin2hex(random_bytes(16)),
            'chance_total' => 2,
            'chance_used' => 1,
            'tier_config' => ['poin' => 65, 'voucher' => 32, 'merch' => 3],
            'awb' => 'AWB-'.bin2hex(random_bytes(4)),
            'gate_source' => 'stub',
            'expires_at' => now()->addDays(30),
        ]);

        $prize = GamePrize::withoutGlobalScopes()->create([
            'game_session_id' => $session->id,
            'slot' => 1,
            'tier' => 'poin',
            'picked' => true,
            'opened' => true,
            'taken' => true,
        ]);

        return Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'brand' => $brand,
            'customer_id' => $customer->id,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.brand' => 'amura']);
    }

    public function test_klaim_kedaluwarsa_di_hanguskan(): void
    {
        $claim = $this->makeClaim('amura', 'belum_redeem', now()->subDay()->toDateTimeString());

        $this->artisan('loyalty:expire-claims')->assertExitCode(0);

        $this->assertSame('hangus', $claim->fresh()->status);
    }

    public function test_klaim_belum_kedaluwarsa_tidak_disentuh(): void
    {
        $claim = $this->makeClaim('amura', 'belum_redeem', now()->addDay()->toDateTimeString());

        $this->artisan('loyalty:expire-claims');

        $this->assertSame('belum_redeem', $claim->fresh()->status);
    }

    public function test_brand_lain_tidak_ikut_di_hanguskan(): void
    {
        $amura = $this->makeClaim('amura', 'belum_redeem', now()->subDay()->toDateTimeString());
        $reglow = $this->makeClaim('reglow', 'belum_redeem', now()->subDay()->toDateTimeString());

        $this->artisan('loyalty:expire-claims');

        $this->assertSame('hangus', $amura->fresh()->status);
        $this->assertSame('belum_redeem', $reglow->fresh()->status, 'klaim brand lain tidak boleh tersentuh');
    }

    public function test_status_selesai_tidak_diubah(): void
    {
        $claim = $this->makeClaim('amura', 'selesai', now()->subDays(10)->toDateTimeString());

        $this->artisan('loyalty:expire-claims');

        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_status_hangus_tetap_hangus_idempotent(): void
    {
        $claim = $this->makeClaim('amura', 'hangus', now()->subDays(10)->toDateTimeString());

        $this->artisan('loyalty:expire-claims');
        $this->artisan('loyalty:expire-claims');

        $this->assertSame('hangus', $claim->fresh()->status);
    }

    public function test_perlu_finance_hangus_tapi_diproses_tidak(): void
    {
        $a = $this->makeClaim('amura', 'perlu_finance', now()->subDay()->toDateTimeString());
        $b = $this->makeClaim('amura', 'diproses', now()->subDay()->toDateTimeString());

        $this->artisan('loyalty:expire-claims');

        $this->assertSame('hangus', $a->fresh()->status);
        $this->assertSame('diproses', $b->fresh()->status);
    }

    public function test_expire_points_command_jalan(): void
    {
        $this->artisan('loyalty:expire-points')->assertExitCode(0);
    }

    public function test_expire_overdue_mengembalikan_jumlah(): void
    {
        $this->makeClaim('amura', 'belum_redeem', now()->subDay()->toDateTimeString());
        $this->makeClaim('amura', 'belum_redeem', now()->subDay()->toDateTimeString());

        $this->assertSame(2, ClaimService::expireOverdue());
    }
}
