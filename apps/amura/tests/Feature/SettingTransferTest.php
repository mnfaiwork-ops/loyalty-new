<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\LoyaltySettings;
use Tests\TestCase;

/** Backup setting: export/import setting game, hanya Super Admin (2026-10-21). */
final class SettingTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Voucher Ongkir', 'tier' => 'voucher', 'is_capped' => false, 'active' => true, 'monthly_stock' => 50]);
        PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Totebag', 'tier' => 'merch', 'is_capped' => false, 'active' => true]);
        PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Philips Air Fryer', 'tier' => 'hadiah_besar', 'is_capped' => true, 'active' => true]);
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(), 'prize_name' => 'Philips Air Fryer', 'cap_units' => 2, 'used_units' => 1,
            'program_start' => '2026-10-27', 'program_end' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $catalog = RewardCatalog::create(['brand' => $this->brand(), 'period' => now()->format('Y-m'), 'published_at' => now()]);
        $catalog->items()->create(['name' => 'Pouch', 'points_cost' => 1000, 'stock' => 100, 'stock_used' => 7, 'active' => true, 'sort' => 1]);
    }

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function otherBrand(): string
    {
        return $this->brand() === 'amura' ? 'reglow' : 'amura';
    }

    private function user(string $role): User
    {
        return User::firstOrCreate(['email' => $role.'@x.id'], [
            'name' => ucfirst($role), 'password' => 'rahasia-panjang', 'role' => $role, 'brand_scope' => $this->brand(),
        ]);
    }

    private function exported(): array
    {
        $response = $this->actingAs($this->user('super_admin'))->get('/admin/setting/export')->assertOk();

        return json_decode($response->streamedContent(), true);
    }

    private function upload(array $data): \Illuminate\Testing\TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('setting.json', json_encode($data));

        return $this->actingAs($this->user('super_admin'))->post('/admin/setting/import', ['file' => $file]);
    }

    private function applyLast(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/admin/setting/import/terapkan', ['token' => session('setting_import.token')]);
    }

    public function test_hanya_super_admin(): void
    {
        foreach (['cs', 'finance', 'game_admin'] as $role) {
            $this->actingAs($this->user($role))->get('/admin/setting')->assertForbidden();
            $this->actingAs($this->user($role))->get('/admin/setting/export')->assertForbidden();
            $this->actingAs($this->user($role))->post('/admin/setting/import')->assertForbidden();
        }
        $this->actingAs($this->user('super_admin'))->get('/admin/setting')->assertOk()->assertSee('Backup setting');
    }

    public function test_export_berisi_setting_tanpa_data_pemakaian(): void
    {
        $data = $this->exported();

        $this->assertSame('loyalty-setting', $data['format']);
        $this->assertSame($this->brand(), $data['brand']);
        $this->assertSame(LoyaltySettings::current()['min_redeem'], $data['points']['min_redeem']);
        $this->assertSame(['Voucher Ongkir', 'Totebag', 'Philips Air Fryer'], array_column($data['prizes'], 'name'));
        $this->assertSame(2, $data['quota_caps'][0]['cap_units']);
        $this->assertArrayNotHasKey('used_units', $data['quota_caps'][0]);
        $this->assertArrayNotHasKey('stock_used', $data['reward_catalogs'][0]['items'][0]);
    }

    public function test_import_menampilkan_perubahan_dulu_lalu_menerapkan(): void
    {
        $data = $this->exported();
        $before = $data['points']['min_redeem'];
        $data['points']['min_redeem'] = 1200;
        $data['game_controls']['order_from'] = '2026-10-27';
        $data['prizes'] = array_values(array_filter($data['prizes'], fn ($p) => $p['name'] !== 'Totebag'));
        $data['prizes'][] = ['name' => 'Produk Serum', 'tier' => 'produk', 'point_value' => null, 'face_value' => 150000, 'monthly_stock' => 10, 'is_capped' => false, 'active' => true];
        $data['quota_caps'][0]['cap_units'] = 3;
        $data['reward_catalogs'][0]['items'][0]['points_cost'] = 900;

        $this->upload($data)->assertOk()
            ->assertSee("Minimal tukar poin: {$before} jadi 1200")
            ->assertSee('Hadiah baru: Produk Serum (produk)')
            ->assertSee('Hadiah Totebag tidak ada di file, akan dinonaktifkan')
            ->assertSee('Kuota Philips Air Fryer, jumlah unit: 2 jadi 3')
            ->assertSee('harga poin: 1000 jadi 900');

        // Belum ada yang berubah sebelum Terapkan.
        $this->assertNotSame(1200, LoyaltySettings::current()['min_redeem']);

        $this->applyLast()->assertRedirect('/admin/setting');

        $this->assertSame(1200, LoyaltySettings::current()['min_redeem']);
        $this->assertFalse(PrizeCatalog::where('name', 'Totebag')->value('active'));
        $this->assertTrue(PrizeCatalog::where('name', 'Produk Serum')->exists());
        $cap = DB::table('quota_caps')->where('prize_name', 'Philips Air Fryer')->first();
        $this->assertSame(3, (int) $cap->cap_units);
        $this->assertSame(1, (int) $cap->used_units, 'unit yang sudah keluar tidak ditimpa');
        $item = RewardCatalog::first()->items()->first();
        $this->assertSame(900, $item->points_cost);
        $this->assertSame(7, $item->stock_used, 'stok terpakai tidak ditimpa');

        $import = DB::table('setting_imports')->first();
        $this->assertSame($this->user('super_admin')->id, (int) $import->user_id);
        Storage::disk('local')->assertExists($import->backup_path);
        $backup = json_decode(Storage::disk('local')->get($import->backup_path), true);
        $this->assertNotSame(1200, $backup['points']['min_redeem'], 'backup berisi setting sebelum import');

        $this->get('/admin/setting/backup/'.$import->id)->assertOk();
    }

    public function test_import_ulang_file_export_tanpa_perubahan(): void
    {
        $this->upload($this->exported())->assertOk()
            ->assertSee('Isi file sama dengan setting sekarang.')
            ->assertDontSee('Terapkan');
    }

    public function test_file_brand_lain_ditolak(): void
    {
        $data = $this->exported();
        $data['brand'] = $this->otherBrand();

        $this->upload($data)->assertRedirect()->assertSessionHasErrors('file');
        $this->assertStringContainsString('bukan '.ucfirst($this->brand()), session('import_errors')[0]);
    }

    public function test_kuota_di_bawah_yang_sudah_keluar_ditolak(): void
    {
        $data = $this->exported();
        $data['quota_caps'][0]['cap_units'] = 0;
        $this->upload($data)->assertRedirect()->assertSessionHasErrors('file');

        $data['quota_caps'][0]['cap_units'] = 1;
        $data['reward_catalogs'][0]['items'][0]['stock'] = 5;
        $this->upload($data)->assertRedirect();
        $this->assertContains('Katalog '.now()->format('Y-m').', "Pouch": stok 5 di bawah yang sudah ditukar (7).', session('import_errors'));
        $this->assertSame(0, DB::table('setting_imports')->count());
    }

    public function test_file_bukan_json_ditolak(): void
    {
        $file = UploadedFile::fake()->createWithContent('setting.json', 'bukan json');

        $this->actingAs($this->user('super_admin'))->post('/admin/setting/import', ['file' => $file])
            ->assertRedirect()->assertSessionHasErrors('file');
    }

    public function test_terapkan_tanpa_pratinjau_atau_token_salah_ditolak(): void
    {
        $this->actingAs($this->user('super_admin'))->post('/admin/setting/import/terapkan', ['token' => 'asal'])
            ->assertRedirect('/admin/setting')->assertSessionHasErrors('file');

        $data = $this->exported();
        $data['points']['min_redeem'] = 1500;
        $this->upload($data)->assertOk();
        $this->post('/admin/setting/import/terapkan', ['token' => 'salah'])->assertSessionHasErrors('file');
        $this->assertNotSame(1500, LoyaltySettings::current()['min_redeem']);
    }

    public function test_pratinjau_kedaluwarsa_setelah_30_menit(): void
    {
        $data = $this->exported();
        $data['points']['min_redeem'] = 1500;
        $this->upload($data)->assertOk();

        $this->travel(31)->minutes();
        $this->applyLast()->assertSessionHasErrors('file');
        $this->assertNotSame(1500, LoyaltySettings::current()['min_redeem']);
    }
}
