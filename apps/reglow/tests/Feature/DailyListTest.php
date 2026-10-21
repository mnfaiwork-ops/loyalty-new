<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;
use Tests\TestCase;

/** Daftar harian BA (CSV) dari data app loyalty, alur funnel 2026-10-20. */
final class DailyListTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function otherBrand(): string
    {
        return $this->brand() === 'reglow' ? 'amura' : 'reglow';
    }

    private function user(string $role): User
    {
        return User::firstOrCreate(['email' => $role.'@x.id'], [
            'name' => ucfirst($role), 'password' => 'rahasia-panjang', 'role' => $role, 'brand_scope' => $this->brand(),
        ]);
    }

    private function customer(string $phone, string $name = 'Pelanggan', ?string $brand = null): Customer
    {
        return Customer::create(['phone' => $phone, 'name' => $name, 'brand' => $brand ?? $this->brand()]);
    }

    private function gameSession(Customer $customer, int $used, int $expiresInDays = 20): void
    {
        DB::table('game_sessions')->insert([
            'customer_id' => $customer->id, 'brand' => $customer->brand, 'session_token' => bin2hex(random_bytes(16)),
            'chance_total' => 2, 'chance_used' => $used, 'tier_config' => json_encode(['poin' => 65, 'voucher' => 32, 'merch' => 3]), 'awb' => 'JX'.random_int(1000, 9999), 'order_amount' => 250000,
            'expires_at' => now()->addDays($expiresInDays), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function claim(Customer $customer, string $prize, int $expiresInDays, string $status = 'belum_redeem'): void
    {
        Claim::create([
            'brand' => $customer->brand, 'customer_id' => $customer->id, 'source' => 'gatcha', 'prize_name' => $prize,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))), 'status' => $status, 'expires_at' => now()->addDays($expiresInDays),
        ]);
    }

    private function csv(string $list): array
    {
        $body = $this->actingAs($this->user('cs'))->get("/admin/daftar-harian/{$list}.csv")->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", ltrim($body, "\xEF\xBB\xBF"))));

        return array_map(fn ($line) => str_getcsv($line, escape: ''), $lines);
    }

    public function test_hanya_cs_dan_super_admin(): void
    {
        $this->actingAs($this->user('game_admin'))->get('/admin/daftar-harian')->assertForbidden();
        $this->actingAs($this->user('finance'))->get('/admin/daftar-harian/saldo-cukup.csv')->assertForbidden();
        $this->actingAs($this->user('cs'))->get('/admin/daftar-harian')->assertOk()->assertSee('Daftar harian BA');
        $this->actingAs($this->user('super_admin'))->get('/admin/daftar-harian')->assertOk();
        $this->actingAs($this->user('cs'))->get('/admin/daftar-harian/lain.csv')->assertNotFound();
    }

    public function test_belum_main_hanya_sesi_aktif_dengan_sisa_kesempatan(): void
    {
        $this->gameSession($this->customer('081200000001', 'Sisa Satu'), 1);
        $this->gameSession($this->customer('081200000002', 'Sudah Habis'), 2);
        $this->gameSession($this->customer('081200000003', 'Kedaluwarsa'), 0, -1);
        $this->gameSession($this->customer('081200000004', 'Brand Lain', $this->otherBrand()), 0);

        $rows = $this->csv('belum-main');

        $this->assertSame(['no_hp', 'nama', 'resi', 'sisa_kesempatan', 'nilai_order', 'masuk_pertama', 'berlaku_sampai'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame(['6281200000001', 'Sisa Satu'], array_slice($rows[1], 0, 2));
        $this->assertSame('1', $rows[1][3]);
    }

    public function test_hadiah_hampir_hangus_dalam_4_hari(): void
    {
        $customer = $this->customer('081200000005', 'Pemenang');
        $this->claim($customer, 'Totebag', 3);
        $this->claim($customer, 'Voucher Ongkir', 10);
        $this->claim($customer, 'Pouch', 2, 'diajukan');

        $rows = $this->csv('hadiah-hampir-hangus');

        $this->assertCount(2, $rows);
        $this->assertSame('Totebag', $rows[1][3]);
        $this->assertSame('3', $rows[1][6]);
    }

    public function test_saldo_cukup_ikut_minimal_tukar_dan_mengabaikan_poin_hangus(): void
    {
        LoyaltySettings::store(['min_redeem' => 800] + LoyaltySettings::current());
        $rich = $this->customer('081200000006', 'Saldo Cukup');
        (new PointWallet($rich))->grant(500, 12);
        (new PointWallet($rich))->grant(400, 12);
        $poor = $this->customer('081200000007', 'Saldo Kurang');
        (new PointWallet($poor))->grant(700, 12);
        $expired = $this->customer('081200000008', 'Poin Hangus');
        (new PointWallet($expired))->grant(900, 12);
        DB::table('point_lots')->where('customer_id', $expired->id)->update(['expires_at' => now()->subDay()]);

        $rows = $this->csv('saldo-cukup');

        $this->assertCount(2, $rows);
        $this->assertSame(['6281200000006', 'Saldo Cukup', '900'], array_slice($rows[1], 0, 3));
    }

    public function test_nama_berawalan_rumus_diamankan(): void
    {
        $this->gameSession($this->customer('081200000009', '=HYPERLINK("x")'), 0);

        $rows = $this->csv('belum-main');

        $this->assertSame('\'=HYPERLINK("x")', $rows[1][1]);
    }

    public function test_daftar_kosong_tetap_punya_header(): void
    {
        $this->assertSame([['no_hp', 'nama', 'saldo_poin', 'poin_terdekat_hangus']], $this->csv('saldo-cukup'));
    }
}
