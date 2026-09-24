<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

final class CsClaimTest extends TestCase
{
    use RefreshDatabase;

    private function csUser(string $brand = 'amura', string $role = 'cs'): User
    {
        return User::create([
            'name' => 'CS '.$brand,
            'email' => 'cs-'.$brand.'-'.$role.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $brand,
        ]);
    }

    private function prize(string $tier = 'poin', string $brand = 'amura'): GamePrize
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
            'tier_config' => [],
            'awb' => 'JX'.random_int(1000, 9999),
            'expires_at' => now()->addDay(),
        ]);

        return GamePrize::create([
            'game_session_id' => $session->id,
            'slot' => 1,
            'tier' => $tier,
        ]);
    }

    public function test_expiry_14_untuk_hadiah_biasa(): void
    {
        $this->assertSame(14, ClaimService::expiryFor('poin'));
        $this->assertSame(14, ClaimService::expiryFor('voucher'));
        $this->assertSame(14, ClaimService::expiryFor('merch'));
    }

    public function test_expiry_30_untuk_hadiah_besar(): void
    {
        $this->assertSame(30, ClaimService::expiryFor('hadiah_besar'));
    }

    public function test_cs_bisa_lihat_dashboard(): void
    {
        $this->actingAs($this->csUser())
            ->get('/admin/claims')
            ->assertOk()
            ->assertSee('Klaim');
    }

    public function test_cs_tidak_bisa_akses_role_lain(): void
    {
        $this->actingAs($this->csUser('amura', 'finance'))
            ->get('/admin/claims')
            ->assertForbidden();
    }

    public function test_resolve_claim(): void
    {
        $cs = $this->csUser();
        $prize = $this->prize('voucher');
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($cs)
            ->post('/admin/claims/'.$claim->claim_code.'/resolve', ['status' => 'diproses'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('diproses', $claim->status);
        $this->assertSame($cs->id, $claim->handled_by);
        $this->assertNotNull($claim->claimed_at);
    }

    public function test_claim_code_unik(): void
    {
        $prize = $this->prize('poin');

        for ($i = 0; $i < 50; $i++) {
            ClaimService::create($prize->session, $prize);
        }

        $codes = Claim::pluck('claim_code');
        $this->assertSame(50, $codes->count());
        $this->assertSame(50, $codes->unique()->count());
    }

    public function test_dashboard_brand_scoped(): void
    {
        $amuraPrize = $this->prize('poin', 'amura');
        ClaimService::create($amuraPrize->session, $amuraPrize);

        $reglowPrize = $this->prize('poin', 'reglow');
        $reglowClaim = ClaimService::create($reglowPrize->session, $reglowPrize);

        $this->actingAs($this->csUser('amura'))
            ->get('/admin/claims')
            ->assertOk()
            ->assertDontSee($reglowClaim->claim_code);
    }

    public function test_hadiah_besar_butuh_finance(): void
    {
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->assertSame('perlu_finance', $claim->status);
    }

    public function test_cs_tidak_bisa_set_selesai_untuk_hadiah_besar(): void
    {
        $cs = $this->csUser();
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($cs)
            ->from('/admin/claims')
            ->post('/admin/claims/'.$claim->claim_code.'/resolve', ['status' => 'selesai'])
            ->assertSessionHasErrors('status');

        $claim->refresh();
        $this->assertSame('perlu_finance', $claim->status);
        $this->assertNull($claim->handled_by);
    }

    public function test_cs_tidak_bisa_set_diproses_untuk_hadiah_besar(): void
    {
        $cs = $this->csUser();
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($cs)
            ->from('/admin/claims')
            ->post('/admin/claims/'.$claim->claim_code.'/resolve', ['status' => 'diproses'])
            ->assertSessionHasErrors('status');

        $claim->refresh();
        $this->assertSame('perlu_finance', $claim->status);
        $this->assertNull($claim->handled_by);
    }

    public function test_cs_bisa_set_hangus_untuk_hadiah_besar(): void
    {
        $cs = $this->csUser();
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($cs)
            ->post('/admin/claims/'.$claim->claim_code.'/resolve', ['status' => 'hangus'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('hangus', $claim->status);
        $this->assertSame($cs->id, $claim->handled_by);
    }

    public function test_cs_bisa_set_selesai_untuk_hadiah_biasa(): void
    {
        $cs = $this->csUser();
        $prize = $this->prize('poin');
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($cs)
            ->post('/admin/claims/'.$claim->claim_code.'/resolve', ['status' => 'selesai'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('selesai', $claim->status);
        $this->assertSame($cs->id, $claim->handled_by);
        $this->assertNotNull($claim->claimed_at);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin/claims')->assertRedirect('/login');
    }

    public function test_login_berhasil(): void
    {
        $user = $this->csUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia'])
            ->assertRedirect('/admin/claims');

        $this->assertAuthenticated();
    }

    public function test_login_gagal(): void
    {
        $user = $this->csUser();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
