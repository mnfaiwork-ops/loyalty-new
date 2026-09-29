<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Models\PointMovement;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;
use Tests\TestCase;

final class PointWalletTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private GameSession $session;
    private int $slot = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create(['phone' => '081200000010', 'brand' => config('app.brand')]);
        $this->session = GameSession::create([
            'customer_id' => $this->customer->id,
            'brand' => config('app.brand'),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX-WALLET',
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function wallet(): PointWallet
    {
        return new PointWallet($this->customer);
    }

    private function lot(int $points = 200, int $months = 12): PointLot
    {
        $prize = GamePrize::create([
            'game_session_id' => $this->session->id,
            'slot' => ++$this->slot,
            'tier' => 'poin',
            'taken' => true,
            'opened' => true,
        ]);

        return $this->wallet()->credit($prize, $points, $months);
    }

    private function catalogClaim(int $cost): Claim
    {
        return Claim::create([
            'brand' => config('app.brand'),
            'customer_id' => $this->customer->id,
            'source' => 'katalog',
            'prize_name' => 'Hadiah uji',
            'points_spent' => $cost,
            'claim_code' => strtoupper(bin2hex(random_bytes(4))),
            'status' => 'belum_redeem',
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function test_pengaturan_default_200_500_12(): void
    {
        $this->assertSame(['per_gift' => 200, 'min_redeem' => 500, 'validity_months' => 12], LoyaltySettings::current());

        LoyaltySettings::store(['per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6]);
        $this->assertSame(['per_gift' => 250, 'min_redeem' => 600, 'validity_months' => 6], LoyaltySettings::current());
        $this->assertSame(LoyaltySettings::DEFAULTS, LoyaltySettings::current('brand-lain'));
    }

    public function test_credit_menambah_saldo_dan_berlaku_12_bulan(): void
    {
        $lot = $this->lot();

        $this->assertSame(200, $this->wallet()->balance());
        $this->assertEqualsWithDelta(now()->addMonths(12)->timestamp, $lot->expires_at->timestamp, 5);
        $this->assertSame(1, PointMovement::where('type', 'earn')->where('points', 200)->count());
    }

    public function test_credit_dua_kali_untuk_kado_sama_tidak_dobel(): void
    {
        $lot = $this->lot();
        $prize = GamePrize::findOrFail($lot->game_prize_id);

        $again = $this->wallet()->credit($prize, 200, 12);

        $this->assertSame($lot->id, $again->id);
        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(1, PointMovement::where('type', 'earn')->count());
    }

    public function test_spend_memakai_lot_paling_cepat_hangus_dulu(): void
    {
        $late = $this->lot(200, 12);
        $early = $this->lot(200, 2);
        $claim = $this->catalogClaim(300);

        $this->wallet()->spend($claim, 300, 0);

        $this->assertSame(0, $early->fresh()->remaining);
        $this->assertSame(100, $late->fresh()->remaining);
        $this->assertSame(100, $this->wallet()->balance());
        $this->assertSame(-300, (int) PointMovement::where('claim_id', $claim->id)->where('type', 'spend')->sum('points'));
    }

    public function test_spend_ditolak_kalau_saldo_di_bawah_minimal(): void
    {
        $this->lot(200);
        $this->lot(200);
        $claim = $this->catalogClaim(300);

        try {
            $this->wallet()->spend($claim, 300, 500);
            $this->fail('harus melempar InsufficientPointsException');
        } catch (InsufficientPointsException $e) {
            $this->assertSame(400, $e->balance);
            $this->assertSame(500, $e->required);
        }

        $this->assertSame(400, $this->wallet()->balance());
    }

    public function test_spend_ditolak_kalau_saldo_kurang_dari_harga(): void
    {
        $this->lot(200);
        $this->lot(200);
        $this->lot(200);

        $this->expectException(InsufficientPointsException::class);
        $this->wallet()->spend($this->catalogClaim(1000), 1000, 500);
    }

    public function test_refund_kembali_ke_lot_asal_hanya_sekali(): void
    {
        $a = $this->lot(200, 2);
        $b = $this->lot(200, 12);
        $claim = $this->catalogClaim(300);
        $this->wallet()->spend($claim, 300, 0);

        $this->assertSame(300, $this->wallet()->refund($claim));
        $this->assertSame(0, $this->wallet()->refund($claim));

        $this->assertSame(200, $a->fresh()->remaining);
        $this->assertSame(200, $b->fresh()->remaining);
        $this->assertSame(400, $this->wallet()->balance());
    }

    public function test_refund_ke_lot_kedaluwarsa_tidak_menambah_saldo(): void
    {
        $a = $this->lot(200, 2);
        $b = $this->lot(200, 12);
        $claim = $this->catalogClaim(300);
        $this->wallet()->spend($claim, 300, 0);
        $a->update(['expires_at' => now()->subDay()]);

        $this->wallet()->refund($claim);

        $this->assertSame(0, $a->fresh()->remaining);
        $this->assertSame(200, $b->fresh()->remaining);
        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(1, PointMovement::where('point_lot_id', $a->id)->where('type', 'expire')->count());
    }

    public function test_expire_due_mengosongkan_lot_lewat_masa_berlaku(): void
    {
        $old = $this->lot(200, 12);
        $this->lot(200, 12);
        $old->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(200, $this->wallet()->balance());
        $this->assertSame(200, PointWallet::expireDue(config('app.brand')));
        $this->assertSame(0, PointWallet::expireDue(config('app.brand')));
        $this->assertSame(0, $old->fresh()->remaining);
    }

    public function test_next_expiring_menunjukkan_lot_terdekat(): void
    {
        $this->assertNull($this->wallet()->nextExpiring());

        $this->lot(200, 12);
        $early = $this->lot(200, 3);

        $next = $this->wallet()->nextExpiring();
        $this->assertSame(200, $next['points']);
        $this->assertSame($early->expires_at->timestamp, $next['expires_at']->timestamp);
    }

    public function test_grant_tanpa_kado_untuk_seeder(): void
    {
        $this->wallet()->grant(600, 12);

        $this->assertSame(600, $this->wallet()->balance());
    }
}
