<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Prizes\ClaimService;
use Tests\TestCase;

final class PlayFlowTest extends TestCase
{
    use RefreshDatabase;

    private function start(): GameSession
    {
        $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789'])
            ->assertRedirect();

        return GameSession::firstOrFail();
    }

    public function test_mulai_game_membuat_sesi(): void
    {
        $this->post('/play/start', ['awb' => 'JX123', 'phone' => '08123456789'])
            ->assertRedirect();

        $this->assertSame(1, GameSession::count());
    }

    public function test_awb_kosong_ditolak(): void
    {
        $this->post('/play/start', ['awb' => '', 'phone' => '08123456789'])
            ->assertSessionHasErrors('awb');
    }

    public function test_pick_3_dan_take_1(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertOk();

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])
            ->assertOk();

        $this->assertTrue((bool) $session->prizes()->where('slot', 1)->value('taken'));
    }

    public function test_chance_habis_tidak_bisa_main_lagi(): void
    {
        $session = $this->start();
        $session->update(['chance_used' => 2]);

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])
            ->assertStatus(422);
    }

    public function test_take_slot_yang_tidak_dipilih_ditolak(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertOk();

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 5])
            ->assertStatus(422);
    }

    public function test_pick_lebih_dari_3_ditolak(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3, 4]])
            ->assertStatus(422);
    }

    public function test_take_slot_tidak_valid_tidak_menghabiskan_chance(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $before = $session->fresh()->chance_used;

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 5])
            ->assertStatus(422);

        $this->assertSame($before, $session->fresh()->chance_used);
    }

    public function test_pick_slot_yang_sudah_dibuka_ditolak(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertStatus(422);
    }

    public function test_reveal_menampilkan_tier_tanpa_menghabiskan_chance(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $this->postJson("/play/{$session->session_token}/reveal")
            ->assertOk()
            ->assertJsonCount(3, 'revealed');

        $this->assertSame(0, $session->fresh()->chance_used);
    }

    public function test_reveal_lalu_take_menyimpan_satu(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $this->postJson("/play/{$session->session_token}/take", ['slot' => 2])
            ->assertOk()
            ->assertJsonPath('chosen', 2);

        $this->assertSame(1, $session->fresh()->chance_used);
        $this->assertTrue((bool) $session->prizes()->where('slot', 2)->value('taken'));
        $this->assertSame(3, $session->prizes()->where('opened', true)->count());
    }

    public function test_take_membuat_klaim(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $chosenSlot = 2;
        $response = $this->postJson("/play/{$session->session_token}/take", ['slot' => $chosenSlot])
            ->assertOk()
            ->assertJsonStructure(['claim_code', 'claim_expires_at']);

        $prizeId = $session->prizes()->where('slot', $chosenSlot)->value('id');
        $claims = Claim::where('game_prize_id', $prizeId)->get();

        $this->assertCount(1, $claims);

        $claim = $claims->first();
        $this->assertNotEmpty($claim->claim_code);
        $this->assertSame($response->json('claim_code'), $claim->claim_code);

        $expectedStatus = $claim->gamePrize->tier === 'hadiah_besar' ? 'perlu_finance' : 'menunggu';
        $this->assertSame($expectedStatus, $claim->status);

        $expectedDays = ClaimService::expiryFor($claim->gamePrize->tier);
        $this->assertEqualsWithDelta($expectedDays, now()->diffInDays($claim->expires_at), 0.1);
    }

    public function test_take_dua_kali_tidak_membuat_dua_klaim(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $first = $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $prize = $session->prizes()->where('slot', 1)->firstOrFail();

        // Simulasi re-take prize yang sama: kembalikan ke state ronde, lalu take lagi.
        $prize->update(['taken' => false, 'picked' => true]);
        $second = $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->assertSame(1, Claim::where('game_prize_id', $prize->id)->count());
        $this->assertSame($first->json('claim_code'), $second->json('claim_code'));
    }

    public function test_take_kedua_kali_ditolak_setelah_chance_habis(): void
    {
        $session = $this->start();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])->assertOk();
        $this->postJson("/play/{$session->session_token}/take", ['slot' => 4])->assertOk();

        $this->postJson("/play/{$session->session_token}/pick", ['slots' => [7, 8, 9]])
            ->assertStatus(422);
        $this->postJson("/play/{$session->session_token}/take", ['slot' => 7])
            ->assertStatus(422);
    }

    public function test_board_tidak_bocorkan_tier(): void
    {
        $session = $this->start();

        $this->get("/play/{$session->session_token}")
            ->assertOk()
            ->assertDontSee('hadiah_besar')
            ->assertDontSee('voucher')
            ->assertDontSee('merch')
            ->assertDontSee('poin');
    }

    public function test_form_menampilkan_halaman(): void
    {
        $this->get('/play')->assertOk();
    }
}
