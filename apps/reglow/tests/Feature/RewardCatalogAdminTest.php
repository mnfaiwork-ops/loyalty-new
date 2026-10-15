<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use Tests\TestCase;

final class RewardCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'game_admin'): User
    {
        return User::create(['name' => 'Admin', 'email' => $role.'@cat.id', 'password' => 'rahasia', 'role' => $role, 'brand_scope' => config('app.brand')]);
    }

    public function test_halaman_katalog_bisa_dibuka_game_admin_saja(): void
    {
        $this->actingAs($this->admin())->get('/admin/catalog')->assertOk()->assertSee('Katalog poin');
        $this->actingAs($this->admin('cs'))->get('/admin/catalog')->assertForbidden();
    }

    public function test_tambah_ubah_item_lalu_terbitkan(): void
    {
        $period = now()->format('Y-m');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/catalog/items', [
            'period' => $period, 'name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 20, 'sort' => 1,
        ])->assertRedirect(route('admin.catalog', ['period' => $period]));

        $item = RewardCatalog::where('period', $period)->firstOrFail()->items()->firstOrFail();
        $this->actingAs($admin)->post("/admin/catalog/items/{$item->id}", [
            'name' => 'Pouch Exclusive', 'points_cost' => 550, 'stock' => '', 'sort' => 1, 'active' => '1',
        ])->assertRedirect();
        $this->assertSame(550, $item->fresh()->points_cost);
        $this->assertNull($item->fresh()->stock);

        $this->actingAs($admin)->post('/admin/catalog/publish', ['period' => $period])->assertRedirect();
        $this->assertNotNull(RewardCatalogService::active(config('app.brand')));
    }

    public function test_salin_dari_bulan_lalu(): void
    {
        $prev = now()->subMonth()->format('Y-m');
        $period = now()->format('Y-m');
        RewardCatalogService::forPeriod(config('app.brand'), $prev)->items()->create(['name' => 'Exclusive ToteBag', 'points_cost' => 700]);

        $this->actingAs($this->admin())->post('/admin/catalog/copy', ['period' => $period])->assertRedirect();

        $this->assertSame(['Exclusive ToteBag'], RewardCatalogService::forPeriod(config('app.brand'), $period)->items->pluck('name')->all());
    }

    public function test_simpan_pengaturan_poin(): void
    {
        $this->actingAs($this->admin())->post('/admin/catalog/settings', [
            'per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6, 'per_thousand' => 2, 'min_percent' => 90,
        ])->assertRedirect();

        $this->assertSame([
            'per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6, 'per_thousand' => 2, 'min_percent' => 90,
        ], LoyaltySettings::current());
    }

    public function test_validasi_harga_poin(): void
    {
        $this->actingAs($this->admin())->post('/admin/catalog/items', [
            'period' => now()->format('Y-m'), 'name' => 'Gratis', 'points_cost' => 0,
        ])->assertSessionHasErrors('points_cost');
    }
}
