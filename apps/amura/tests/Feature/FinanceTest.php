<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\User;
use Tests\TestCase;

final class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $brand = 'amura'): User
    {
        return User::create([
            'name' => ucfirst($role).' '.$brand,
            'email' => $role.'-'.$brand.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $brand,
        ]);
    }

    private function claim(string $brand = 'amura', string $tier = 'hadiah_besar', string $status = 'perlu_finance'): Claim
    {
        $customer = Customer::create([
            'phone' => '08'.random_int(100000000, 999999999),
            'name' => 'Pelanggan',
            'brand' => $brand,
        ]);

        $session = GameSession::create([
            'customer_id' => $customer->id,
            'brand' => $brand,
            'session_token' => bin2hex(random_bytes(8)),
            'chance_total' => 2,
            'chance_used' => 0,
            'tier_config' => [],
            'awb' => 'JX'.random_int(1000, 9999),
            'expires_at' => now()->addDay(),
        ]);

        $prize = GamePrize::create([
            'game_session_id' => $session->id,
            'slot' => 1,
            'tier' => $tier,
            'taken' => true,
            'opened' => true,
        ]);

        return Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_finance_bisa_lihat_dashboard(): void
    {
        $this->actingAs($this->user('finance'))
            ->get('/admin/finance')
            ->assertOk();
    }

    public function test_cs_tidak_bisa_akses_finance(): void
    {
        $this->actingAs($this->user('cs'))
            ->get('/admin/finance')
            ->assertForbidden();
    }

    public function test_game_admin_tidak_bisa_akses_finance(): void
    {
        $this->actingAs($this->user('game_admin'))
            ->get('/admin/finance')
            ->assertForbidden();
    }

    public function test_super_admin_bisa_akses_finance(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->get('/admin/finance')
            ->assertOk();
    }

    public function test_finance_setujui_hadiah_besar(): void
    {
        $finance = $this->user('finance');
        $claim = $this->claim();

        $this->actingAs($finance)
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('diproses', $claim->status);
        $this->assertSame($finance->id, $claim->finance_by);
        $this->assertNotNull($claim->claimed_at);
    }

    public function test_approve_claim_biasa_ditolak(): void
    {
        $claim = $this->claim('amura', 'voucher', 'belum_redeem');

        $this->actingAs($this->user('finance'))
            ->from('/admin/finance')
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertSessionHasErrors('status');

        $this->assertSame('belum_redeem', $claim->refresh()->status);
    }

    public function test_approve_claim_tidak_ada_pesan_indonesia(): void
    {
        $this->actingAs($this->user('finance'))
            ->from('/admin/finance')
            ->post('/admin/finance/TIDAKADA/approve')
            ->assertSessionHasErrors('status');
    }

    public function test_approve_brand_lain_ditolak(): void
    {
        $reglow = $this->claim('reglow');

        $this->actingAs($this->user('finance', 'amura'))
            ->from('/admin/finance')
            ->post('/admin/finance/'.$reglow->claim_code.'/approve')
            ->assertSessionHasErrors('status');

        $reglow->refresh();
        $this->assertSame('perlu_finance', $reglow->status);
        $this->assertNull($reglow->finance_by);
    }

    public function test_approve_dua_kali_ditolak(): void
    {
        $finance = $this->user('finance');
        $claim = $this->claim();

        $this->actingAs($finance)
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertRedirect();

        $this->actingAs($finance)
            ->from('/admin/finance')
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertSessionHasErrors('status');

        $claim->refresh();
        $this->assertSame('diproses', $claim->status);
        $this->assertSame($finance->id, $claim->finance_by);
    }

    public function test_hadiah_besar_diproses_tetap_di_pending_finance(): void
    {
        $finance = $this->user('finance');
        $claim = $this->claim();

        $this->actingAs($finance)
            ->get('/admin/finance')
            ->assertOk()
            ->assertSee($claim->claim_code);

        $this->actingAs($finance)
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertRedirect();

        $this->assertSame('diproses', $claim->refresh()->status);
    }

    public function test_finance_approve_claim_tidak_approvable_pesan_indonesia(): void
    {
        $claim = $this->claim('amura', 'hadiah_besar', 'selesai');

        $this->actingAs($this->user('finance'))
            ->from('/admin/finance')
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertSessionHasErrors('status')
            ->assertStatus(302);
    }

    public function test_post_approve_ditolak_untuk_role_lain(): void
    {
        $claim = $this->claim();

        $this->actingAs($this->user('cs'))
            ->post('/admin/finance/'.$claim->claim_code.'/approve')
            ->assertForbidden();
    }

    public function test_dashboard_brand_scoped(): void
    {
        $amura = $this->claim('amura');
        $reglow = $this->claim('reglow');

        $this->actingAs($this->user('finance', 'amura'))
            ->get('/admin/finance')
            ->assertOk()
            ->assertSee($amura->claim_code)
            ->assertDontSee($reglow->claim_code);
    }
}
