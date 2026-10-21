<?php

namespace Tests\Feature;

use App\Support\PhoneInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Prizes\BigPrizeQuota;
use LoyaltyNew\Core\Settings\SettingsTransfer;
use Tests\TestCase;

/** Kuota bersama hadiah utama: lima jenis Philips tampil, totalnya ikut satu kuota (Fai 2026-10-21). */
final class SharedQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = ['Philips Hairdryer', 'Philips Setrika', 'Philips Mixer', 'Philips Rice Cooker', 'Philips Air Fryer'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::TYPES as $name) {
            PrizeCatalog::create([
                'brand' => $this->brand(), 'name' => $name, 'tier' => 'hadiah_besar', 'is_capped' => true,
                'active' => true, 'quota_name' => 'Philips',
            ]);
        }
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(), 'prize_name' => 'Philips', 'cap_units' => 1, 'used_units' => 0,
            'program_start' => now()->subDay()->toDateString(), 'program_end' => now()->addMonth()->toDateString(),
            'release_from' => now()->subDay()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function repeatSession(string $phone): GameSession
    {
        $this->previousOrder($phone);

        return app(SessionService::class)->open('JX-'.$phone, PhoneInput::normalize($phone));
    }

    /** Pilih kotak hadiah utama + 2 lain di kesempatan 1 lalu buka. */
    private function revealBigPrize(GameSession $session): void
    {
        $big = $session->prizes()->where('round', 1)->where('tier', 'hadiah_besar')->value('slot');
        $slots = $session->prizes()->where('round', 1)->where('slot', '!=', $big)->limit(2)->pluck('slot')->push($big)->all();
        $this->playAs($session)->postJson("/play/{$session->session_token}/pick", ['slots' => $slots])->assertOk();
        $this->playAs($session)->postJson("/play/{$session->session_token}/reveal")->assertOk();
    }

    public function test_jenis_philips_berbagi_satu_kuota(): void
    {
        $this->alwaysPlaceBigPrize();

        $first = $this->repeatSession('081200000011');
        $catalogId = $first->prizes()->where('tier', 'hadiah_besar')->value('prize_catalog_id');
        $this->assertContains(PrizeCatalog::find($catalogId)->name, self::TYPES);

        $this->revealBigPrize($first);
        $this->assertSame(1, (int) DB::table('quota_caps')->where('prize_name', 'Philips')->value('used_units'));
        $this->assertSame(0, DB::table('quota_caps')->whereIn('prize_name', self::TYPES)->count());

        // Kuota bersama habis: tidak ada jenis Philips lain yang ditaruh di papan berikutnya.
        $second = $this->repeatSession('081200000012');
        $this->assertSame(0, $second->prizes()->where('tier', 'hadiah_besar')->count());
        $this->assertFalse(BigPrizeQuota::available($this->brand(), PrizeCatalog::where('name', 'Philips Mixer')->first()));
    }

    public function test_jenis_yang_keluar_diacak(): void
    {
        DB::table('quota_caps')->where('prize_name', 'Philips')->update(['cap_units' => 100]);
        $this->alwaysPlaceBigPrize();

        $names = collect(range(21, 40))->map(function (int $i) {
            $id = $this->repeatSession('0812000000'.$i)->prizes()->where('tier', 'hadiah_besar')->value('prize_catalog_id');

            return PrizeCatalog::find($id)->name;
        });

        $this->assertGreaterThan(1, $names->unique()->count());
    }

    public function test_tambah_hadiah_ke_kuota_bersama_tanpa_baris_kuota_baru(): void
    {
        $admin = User::create(['name' => 'GA', 'email' => 'ga@x.id', 'password' => 'rahasia', 'role' => 'game_admin', 'brand_scope' => $this->brand()]);

        $this->actingAs($admin)->post('/admin/game/catalog', ['name' => 'Philips Blender', 'tier' => 'hadiah_besar', 'quota_name' => 'Philips'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Philips', PrizeCatalog::where('name', 'Philips Blender')->value('quota_name'));
        $this->assertSame(1, DB::table('quota_caps')->where('brand', $this->brand())->count());

        $this->actingAs($admin)->post('/admin/game/catalog', ['name' => 'Emas Antam', 'tier' => 'hadiah_besar'])
            ->assertSessionHasErrors('cap_units');

        $this->actingAs($admin)->get('/admin/game')->assertOk()->assertSee('Kuota bersama: Philips');
    }

    public function test_backup_setting_membawa_kuota_bersama(): void
    {
        $file = SettingsTransfer::export($this->brand());
        $this->assertSame(['Philips'], array_values(array_unique(array_column($file['prizes'], 'quota_name'))));
        $this->assertSame(['Philips'], array_column($file['quota_caps'], 'prize_name'));

        $file['quota_caps'][0]['cap_units'] = 2;
        SettingsTransfer::apply(SettingsTransfer::inspect($file, $this->brand())['data'], $this->brand());
        $this->assertSame(2, (int) DB::table('quota_caps')->where('prize_name', 'Philips')->value('cap_units'));

        // Hadiah utama yang menunjuk kuota bersama yang tidak ada di file ditolak.
        $file['quota_caps'] = [];
        $this->expectException(\LoyaltyNew\Core\Settings\InvalidSettingsFile::class);
        SettingsTransfer::inspect($file, $this->brand());
    }

    public function test_landing_page_menampilkan_semua_jenis_philips(): void
    {
        $response = $this->get('/cara-main')->assertOk();

        foreach (self::TYPES as $name) {
            $response->assertSee($name);
        }
    }
}
