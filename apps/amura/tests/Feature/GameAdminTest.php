<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\TierConfig;
use Tests\TestCase;

final class GameAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'game_admin'): User
    {
        return User::create([
            'name' => 'GA',
            'email' => $role.'@x.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => 'amura',
        ]);
    }

    public function test_game_admin_bisa_lihat_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin/game')->assertOk();
    }

    public function test_tambah_katalog_hadiah(): void
    {
        $this->actingAs($this->admin())->post('/admin/game/catalog', [
            'name' => 'Voucher Ongkir 20rb',
            'tier' => 'voucher',
            'face_value' => 20000,
            'quota_total' => 1000,
        ])->assertRedirect();

        $this->assertSame(1, PrizeCatalog::count());
        $this->assertSame('amura', PrizeCatalog::first()->brand);
    }

    public function test_cs_tidak_bisa_akses_game_admin(): void
    {
        $this->actingAs($this->admin('cs'))->get('/admin/game')->assertForbidden();
    }

    public function test_super_admin_bisa_akses(): void
    {
        $this->actingAs($this->admin('super_admin'))->get('/admin/game')->assertOk();
    }

    public function test_update_tier_config_persists(): void
    {
        $this->actingAs($this->admin())->post('/admin/game/tiers', [
            'poin' => 70,
            'voucher' => 25,
            'merch' => 5,
        ])->assertRedirect();

        $this->assertSame(['poin' => 70, 'voucher' => 25, 'merch' => 5], TierConfig::current());

        $session = app(SessionService::class)->open('JX999', '081100000099');
        $this->assertSame(['poin' => 70, 'voucher' => 25, 'merch' => 5], $session->tier_config);
    }

    public function test_tambah_hadiah_besar_dengan_cap(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/game/catalog', [
            'name' => 'Emas 1 gram',
            'tier' => 'hadiah_besar',
            'is_capped' => 1,
            'cap_units' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('quota_caps', [
            'brand' => 'amura',
            'prize_name' => 'Emas 1 gram',
            'cap_units' => 1,
        ]);

        $this->actingAs($admin)
            ->get('/admin/game')
            ->assertOk()
            ->assertSee('0 dari 1 unit');
    }

    public function test_catalog_brand_scoped(): void
    {
        PrizeCatalog::create([
            'brand' => 'reglow',
            'name' => 'Hadiah Reglow Unik',
            'tier' => 'poin',
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/game')
            ->assertOk()
            ->assertDontSee('Hadiah Reglow Unik');
    }

    public function test_hadiah_besar_tanpa_cap_units_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/game')
            ->post('/admin/game/catalog', [
                'name' => 'Philips Air Fryer',
                'tier' => 'hadiah_besar',
                'is_capped' => 1,
            ])
            ->assertSessionHasErrors('cap_units');

        $this->assertDatabaseCount('prize_catalog', 0);
        $this->assertDatabaseCount('quota_caps', 0);
    }

    public function test_hadiah_besar_selalu_capped(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/game/catalog', [
                'name' => 'Philips Air Fryer',
                'tier' => 'hadiah_besar',
                'cap_units' => 2,
            ])->assertRedirect();

        $prize = PrizeCatalog::where('name', 'Philips Air Fryer')->firstOrFail();
        $this->assertTrue($prize->is_capped);

        $this->assertDatabaseHas('quota_caps', [
            'brand' => 'amura',
            'prize_name' => 'Philips Air Fryer',
            'cap_units' => 2,
        ]);
    }

    public function test_tier_config_default_kalau_belum_diatur(): void
    {
        $this->assertSame(['poin' => 65, 'voucher' => 32, 'merch' => 3], TierConfig::current());
    }

    public function test_tier_config_terisolasi_per_brand(): void
    {
        $this->actingAs($this->admin())->post('/admin/game/tiers', [
            'poin' => 70,
            'voucher' => 25,
            'merch' => 5,
        ])->assertRedirect();

        config(['app.brand' => 'reglow']);
        $this->assertSame(['poin' => 65, 'voucher' => 32, 'merch' => 3], TierConfig::current());
        config(['app.brand' => 'amura']);

        $this->assertSame(['poin' => 70, 'voucher' => 25, 'merch' => 5], TierConfig::current());
    }

    public function test_login_mengarahkan_sesuai_role(): void
    {
        $this->post('/login', ['email' => $this->admin('game_admin')->email, 'password' => 'rahasia'])
            ->assertRedirect('/admin/game');

        auth()->logout();

        $this->post('/login', ['email' => $this->admin('cs')->email, 'password' => 'rahasia'])
            ->assertRedirect('/admin/claims');
    }
}
