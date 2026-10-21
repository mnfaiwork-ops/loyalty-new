<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\TierConfig;
use LoyaltyNew\Core\Settings\SettingsTransfer;
use Tests\TestCase;

/** File setting rilis 22 Okt 2026 (docs/deploy) harus bisa di-import ke DB produksi tanpa error. */
final class ReleaseSettingFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_setting_rilis_bisa_diimport_ke_db_kosong(): void
    {
        $this->travelTo('2026-10-22 10:00:00');
        $brand = (string) config('app.brand');
        $file = json_decode(file_get_contents(base_path('../../docs/deploy/2026-10-22/setting-'.$brand.'.json')), true);

        $checked = SettingsTransfer::inspect($file, $brand);
        SettingsTransfer::apply($checked['data'], $brand);

        $this->assertSame(18, PrizeCatalog::where('brand', $brand)->where('active', true)->count());
        $this->assertSame(5, PrizeCatalog::where('brand', $brand)->where('quota_name', 'Philips')->count());
        $this->assertSame(2, (int) DB::table('quota_caps')->where('brand', $brand)->where('prize_name', 'Philips')->value('cap_units'));
        $this->assertSame(91, TierConfig::current('lama', $brand)['poin']);
        $this->assertSame(89, TierConfig::current('baru', $brand)['poin']);
        $this->assertSame([], SettingsTransfer::inspect($file, $brand)['changes']);
    }
}
