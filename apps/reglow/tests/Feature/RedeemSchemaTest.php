<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

final class RedeemSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_dan_kolom_baru_ada(): void
    {
        foreach (['claim_events', 'point_lots', 'point_movements', 'reward_catalogs', 'reward_catalog_items'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "tabel {$table} harus ada");
        }

        foreach (['brand', 'customer_id', 'source', 'prize_name', 'reward_catalog_item_id', 'points_spent',
            'redeem_requested_at', 'shipping_awb', 'rejected_reason'] as $column) {
            $this->assertTrue(Schema::hasColumn('claims', $column), "kolom claims.{$column} harus ada");
        }
    }

    public function test_klaim_gatcha_otomatis_mendapat_brand_dan_customer_dari_sesi(): void
    {
        $customer = Customer::create(['phone' => '081200000001', 'brand' => config('app.brand')]);
        $session = GameSession::create([
            'customer_id' => $customer->id,
            'brand' => config('app.brand'),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX-SCHEMA',
            'expires_at' => now()->addDay(),
        ]);
        $prize = GamePrize::create(['game_session_id' => $session->id, 'slot' => 1, 'tier' => 'voucher']);

        $claim = Claim::create([
            'game_session_id' => $session->id,
            'game_prize_id' => $prize->id,
            'claim_code' => 'SCHEMA01',
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertSame(config('app.brand'), $claim->brand);
        $this->assertSame($customer->id, $claim->customer_id);
        $this->assertSame('gatcha', $claim->fresh()->source);
        $this->assertSame(14, $claim->daysLeft());
    }

    public function test_klaim_katalog_boleh_tanpa_sesi(): void
    {
        $customer = Customer::create(['phone' => '081200000002', 'brand' => config('app.brand')]);

        $claim = Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $customer->id,
            'source' => 'katalog',
            'prize_name' => 'Pouch Exclusive',
            'claim_code' => 'SCHEMA02',
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);

        $this->assertNull($claim->game_session_id);
        $this->assertFalse($claim->isBigPrize());
        $this->assertSame('0812••••0002', $customer->maskedPhone());
    }
}
