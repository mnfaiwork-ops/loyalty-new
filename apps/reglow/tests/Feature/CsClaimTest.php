<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

final class CsClaimTest extends TestCase
{
    use RefreshDatabase;

    private function csUser(string $brand = 'reglow', string $role = 'cs'): User
    {
        return User::create([
            'name' => 'CS '.$brand,
            'email' => 'cs-'.$brand.'-'.$role.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $brand,
        ]);
    }

    private function prize(string $tier = 'poin', string $brand = 'reglow'): GamePrize
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

        $prize = GamePrize::create([
            'game_session_id' => $session->id,
            'slot' => 1,
            'tier' => $tier,
        ]);

        // Hubungkan sesi yang baru dibuat langsung ke objek, agar test yang
        // sengaja menyiapkan brand lain tidak perlu query lintas brand
        // (relasi $prize->session sendiri ter-scope BrandScope).
        $prize->setRelation('session', $session);

        return $prize;
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
        $this->actingAs($this->csUser('reglow', 'finance'))
            ->get('/admin/claims')
            ->assertForbidden();
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

    public function test_klaim_baru_selalu_belum_redeem_termasuk_hadiah_besar(): void
    {
        $prize = $this->prize('hadiah_besar');
        $claim = ClaimService::create($prize->session, $prize);

        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('Hadiah Utama', $claim->prize_name);
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

    private function submitted(string $tier = 'voucher', string $brand = 'reglow'): Claim
    {
        $prize = $this->prize($tier, $brand);
        $claim = ClaimService::create($prize->session, $prize);
        $claim->update(['status' => 'diajukan', 'redeem_requested_at' => now()]);

        return $claim->fresh();
    }

    private function winnerPhone(Claim $claim): string
    {
        return \LoyaltyNew\Core\Models\Customer::withoutGlobalScopes()->whereKey($claim->customer_id)->value('phone');
    }

    public function test_antrian_hanya_klaim_diajukan_brand_ini(): void
    {
        $waiting = $this->submitted();
        $fresh = ClaimService::create(($p = $this->prize())->session, $p);
        $amura = $this->submitted('voucher', 'amura');

        $this->actingAs($this->csUser())->get('/admin/claims')
            ->assertOk()
            ->assertSee($waiting->claim_code)
            ->assertDontSee($fresh->claim_code)
            ->assertDontSee($amura->claim_code);
    }

    public function test_cari_kode_menemukan_klaim_status_apa_pun(): void
    {
        $prize = $this->prize();
        $claim = ClaimService::create($prize->session, $prize);

        $this->actingAs($this->csUser())->get('/admin/claims?q='.$claim->claim_code)
            ->assertOk()
            ->assertSee($claim->claim_code);
    }

    public function test_detail_menampilkan_nama_dan_nomor_pemenang(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())->get('/admin/claims/'.$claim->claim_code)
            ->assertOk()
            ->assertSee('Pelanggan')
            ->assertSee($this->winnerPhone($claim));
    }

    public function test_konfirmasi_nomor_cocok(): void
    {
        $cs = $this->csUser();
        $claim = $this->submitted();

        $this->actingAs($cs)
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => $this->winnerPhone($claim), 'note' => 'alamat dicek'])
            ->assertRedirect(route('admin.claims'));

        $this->assertSame('diproses', $claim->fresh()->status);
        $this->assertSame($cs->id, $claim->fresh()->handled_by);
    }

    public function test_konfirmasi_nomor_beda_ditolak(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())
            ->from('/admin/claims/'.$claim->claim_code)
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => '089999999999'])
            ->assertSessionHasErrors('status');

        $this->assertSame('diajukan', $claim->fresh()->status);
    }

    public function test_tolak_nomor_beda_kembali_ke_belum_redeem(): void
    {
        $claim = $this->submitted();

        $this->actingAs($this->csUser())
            ->post('/admin/claims/'.$claim->claim_code.'/reject', ['sender_phone' => '089999999999'])
            ->assertRedirect(route('admin.claims'));

        $this->assertSame('belum_redeem', $claim->fresh()->status);
        $this->assertSame(ClaimFlow::REJECT_REASON, $claim->fresh()->rejected_reason);
    }

    public function test_hadiah_utama_dikonfirmasi_ke_finance(): void
    {
        $claim = $this->submitted('hadiah_besar');

        $this->actingAs($this->csUser())
            ->post('/admin/claims/'.$claim->claim_code.'/confirm', ['sender_phone' => $this->winnerPhone($claim)]);

        $this->assertSame('perlu_finance', $claim->fresh()->status);
    }

    public function test_klaim_brand_lain_404(): void
    {
        $amura = $this->submitted('voucher', 'amura');

        $this->actingAs($this->csUser())->get('/admin/claims/'.$amura->claim_code)->assertNotFound();
    }
}
