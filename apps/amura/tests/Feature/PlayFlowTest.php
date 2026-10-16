<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PrizeCatalog;
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

    private function forceTiers(GameSession $session, array $slots, string $tier): void
    {
        $session->prizes()->whereIn('slot', $slots)->update(['tier' => $tier, 'prize_catalog_id' => null]);
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

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])
            ->assertOk();

        $this->assertTrue((bool) $session->prizes()->where('slot', 1)->value('taken'));
    }

    public function test_chance_habis_tidak_bisa_main_lagi(): void
    {
        $session = $this->start();
        $session->update(['chance_used' => 2]);

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])
            ->assertStatus(422);
    }

    public function test_take_slot_yang_tidak_dipilih_ditolak(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])
            ->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 5])
            ->assertStatus(422);
    }

    public function test_pick_lebih_dari_3_ditolak(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3, 4]])
            ->assertStatus(422);
    }

    public function test_take_slot_tidak_valid_tidak_menghabiskan_chance(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $before = $session->fresh()->chance_used;

        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 5])
            ->assertStatus(422);

        $this->assertSame($before, $session->fresh()->chance_used);
    }

    public function test_kesempatan_kedua_memakai_24_kado_baru(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $round2 = $session->prizes()->where('round', 2);
        $this->assertSame(24, (clone $round2)->count());
        $this->assertSame(0, (clone $round2)->where(fn ($q) => $q->where('opened', true)->orWhere('picked', true))->count());

        // Slot yang sama boleh dipilih lagi karena isinya baru.
        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->assertSame(3, (clone $round2)->where('picked', true)->count());
    }

    public function test_pick_kedua_di_kesempatan_yang_sama_ditolak(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])
            ->assertStatus(422);
    }

    public function test_papan_kesempatan_kedua_tidak_diberi_hadiah_utama_lagi_setelah_menang(): void
    {
        $catalog = PrizeCatalog::create([
            'brand' => config('app.brand'), 'name' => 'Emas 1 gram', 'tier' => 'hadiah_besar',
            'is_capped' => true, 'quota_total' => 5, 'active' => true,
        ]);
        $this->alwaysPlaceBigPrize();
        \Illuminate\Support\Facades\DB::table('quota_caps')->insert([
            'brand' => config('app.brand'), 'prize_name' => 'Emas 1 gram', 'cap_units' => 5, 'used_units' => 0,
            'program_start' => now()->subDay()->toDateString(), 'program_end' => now()->addMonth()->toDateString(),
            'release_from' => now()->subDay()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $session = $this->start();
        $this->forceTiers($session, [1], 'hadiah_besar');
        $session->prizes()->where('slot', 1)->update(['prize_catalog_id' => $catalog->id]);

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk()->assertJson(['auto_taken' => true]);

        $this->assertSame(0, $session->prizes()->where('round', 2)->where('tier', 'hadiah_besar')->count());
    }

    public function test_reveal_menampilkan_tier_tanpa_menghabiskan_chance(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")
            ->assertOk()
            ->assertJsonCount(3, 'revealed');

        $this->assertSame(0, $session->fresh()->chance_used);
    }

    public function test_reveal_lalu_take_menyimpan_satu(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 2])
            ->assertOk()
            ->assertJsonPath('chosen', 2);

        $this->assertSame(1, $session->fresh()->chance_used);
        $this->assertTrue((bool) $session->prizes()->where('slot', 2)->value('taken'));
        $this->assertSame(3, $session->prizes()->where('opened', true)->count());
    }

    /** Kado voucher butuh item aktif di katalog Gatcha; tanpa item, kotaknya jadi poin. */
    private function voucherItem(): PrizeCatalog
    {
        return PrizeCatalog::create([
            'brand' => config('app.brand'), 'name' => 'Voucher Gratis Ongkir', 'tier' => 'voucher', 'active' => true,
        ]);
    }

    public function test_take_membuat_klaim(): void
    {
        $session = $this->start();
        $this->voucherItem();
        $this->forceTiers($session, [1, 2, 3], 'voucher');

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $chosenSlot = 2;
        $response = $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => $chosenSlot])
            ->assertOk()
            ->assertJsonStructure(['claim_code', 'claim_expires_at']);

        $prizeId = $session->prizes()->where('slot', $chosenSlot)->value('id');
        $claims = Claim::where('game_prize_id', $prizeId)->get();

        $this->assertCount(1, $claims);

        $claim = $claims->first();
        $this->assertNotEmpty($claim->claim_code);
        $this->assertSame($response->json('claim_code'), $claim->claim_code);

        $this->assertSame('belum_redeem', $claim->status);

        $expectedDays = ClaimService::expiryFor($claim->gamePrize->tier);
        $this->assertEqualsWithDelta($expectedDays, now()->diffInDays($claim->expires_at), 0.1);
    }

    public function test_take_dua_kali_tidak_membuat_dua_klaim(): void
    {
        $session = $this->start();
        $this->voucherItem();
        $this->forceTiers($session, [1, 2, 3], 'voucher');

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $first = $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $prize = $session->prizes()->where('slot', 1)->firstOrFail();

        // Simulasi re-take prize yang sama: kembalikan ke state ronde, lalu take lagi.
        $prize->update(['taken' => false, 'picked' => true]);
        $session->fresh()->update(['chance_used' => 0]);
        $second = $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->assertSame(1, Claim::where('game_prize_id', $prize->id)->count());
        $this->assertSame($first->json('claim_code'), $second->json('claim_code'));
    }

    public function test_take_kedua_kali_ditolak_setelah_chance_habis(): void
    {
        $session = $this->start();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [4, 5, 6]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 4])->assertOk();

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [7, 8, 9]])
            ->assertStatus(422);
        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 7])
            ->assertStatus(422);
    }

    public function test_board_tidak_bocorkan_tier(): void
    {
        $session = $this->start();

        $this->playAs($session)->get("/play/{$session->session_token}")
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

    public function test_take_kado_poin_masuk_saldo_tanpa_klaim(): void
    {
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 2])
            ->assertOk()
            ->assertJsonPath('points', 200)
            ->assertJsonPath('balance', 200)
            ->assertJsonPath('claim_code', null);

        $this->assertSame(0, Claim::count());
        $this->assertSame(1, PointLot::count());
    }

    public function test_reveal_voucher_diberi_nama_item_katalog(): void
    {
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Voucher Gratis Ongkir', 'tier' => 'voucher', 'active' => true]);
        PrizeCatalog::create(['brand' => config('app.brand'), 'name' => 'Item Nonaktif', 'tier' => 'voucher', 'active' => false]);
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'voucher');

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $response = $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk();

        $this->assertSame(['Voucher Gratis Ongkir'], array_values(array_unique(array_column($response->json('revealed'), 'name'))));

        $this->playAs($session)->postJson("/play/{$session->session_token}/take", ['slot' => 1])
            ->assertOk()
            ->assertJsonPath('prize_name', 'Voucher Gratis Ongkir');
        $this->assertSame('Voucher Gratis Ongkir', Claim::firstOrFail()->prize_name);
    }

    public function test_reveal_kado_poin_menyertakan_nilai_poin(): void
    {
        $session = $this->start();
        $this->forceTiers($session, [1, 2, 3], 'poin');

        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => [1, 2, 3]])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")
            ->assertOk()
            ->assertJsonPath('revealed.0.points', 200);
    }
}
