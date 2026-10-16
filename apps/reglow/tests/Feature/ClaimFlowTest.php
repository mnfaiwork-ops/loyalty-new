<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\ClaimEvent;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Models\User;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;
use Tests\TestCase;

final class ClaimFlowTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function staff(string $role): User
    {
        return User::create([
            'name' => $role,
            'email' => $role.'@flow.id',
            'password' => 'rahasia',
            'role' => $role,
            'brand_scope' => $this->brand(),
        ]);
    }

    private function claim(string $tier = 'voucher', string $status = 'belum_redeem', string $phone = '081234567890'): Claim
    {
        $customer = Customer::firstOrCreate(['phone' => $phone, 'brand' => $this->brand()], ['name' => 'Sari']);
        $session = GameSession::create([
            'customer_id' => $customer->id,
            'brand' => $this->brand(),
            'session_token' => bin2hex(random_bytes(8)),
            'tier_config' => [],
            'awb' => 'JX'.random_int(10000, 99999),
            'expires_at' => now()->addDays(30),
        ]);
        $prize = GamePrize::create(['game_session_id' => $session->id, 'slot' => 1, 'tier' => $tier, 'taken' => true, 'opened' => true]);
        $claim = ClaimService::create($session, $prize);
        if ($status !== 'belum_redeem') {
            $claim->update(['status' => $status]);
        }

        return $claim->fresh();
    }

    public function test_request_mengubah_ke_diajukan_dan_mencatat_event(): void
    {
        $claim = $this->claim();

        ClaimFlow::request($claim, $claim->customer);

        $claim->refresh();
        $this->assertSame('diajukan', $claim->status);
        $this->assertNotNull($claim->redeem_requested_at);
        $this->assertSame(['belum_redeem', 'diajukan'], $claim->events->pluck('to_status')->all());
        $this->assertSame('customer', $claim->events->last()->actor);
    }

    public function test_request_klaim_milik_pelanggan_lain_ditolak(): void
    {
        $claim = $this->claim();
        $other = Customer::create(['phone' => '089900000000', 'brand' => $this->brand()]);

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::request($claim, $other);
    }

    public function test_request_lewat_batas_ditolak(): void
    {
        $claim = $this->claim();
        $claim->update(['expires_at' => now()->subMinute()]);

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::request($claim, $claim->customer);
    }

    public function test_confirm_nomor_cocok_walau_formatnya_beda(): void
    {
        $claim = $this->claim(status: 'diajukan');
        $cs = $this->staff('cs');

        ClaimFlow::confirm($claim, $cs, '+62 812-3456-7890', 'alamat Kendari dicek');

        $claim->refresh();
        $this->assertSame('diproses', $claim->status);
        $this->assertSame($cs->id, $claim->handled_by);
        $this->assertSame('alamat Kendari dicek', $claim->events->last()->note);
    }

    public function test_confirm_hadiah_utama_ke_perlu_finance(): void
    {
        $claim = $this->claim('hadiah_besar', 'diajukan');

        ClaimFlow::confirm($claim, $this->staff('cs'), '081234567890');

        $this->assertSame('perlu_finance', $claim->fresh()->status);
    }

    public function test_confirm_nomor_beda_ditolak_status_tetap(): void
    {
        $claim = $this->claim(status: 'diajukan');

        try {
            ClaimFlow::confirm($claim, $this->staff('cs'), '081111111111');
            $this->fail('harus ditolak');
        } catch (ClaimTransitionException $e) {
            $this->assertStringContainsString('tidak cocok', $e->getMessage());
        }

        $this->assertSame('diajukan', $claim->fresh()->status);
    }

    public function test_reject_kembali_ke_belum_redeem_tanpa_perpanjang_batas(): void
    {
        $claim = $this->claim(status: 'diajukan');
        $expires = $claim->expires_at->timestamp;

        ClaimFlow::reject($claim, $this->staff('cs'), '081111111111');

        $claim->refresh();
        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('Nomor pengirim tidak cocok', $claim->rejected_reason);
        $this->assertSame($expires, $claim->expires_at->timestamp);
    }

    public function test_reject_nomor_sama_ditolak(): void
    {
        $claim = $this->claim(status: 'diajukan');

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::reject($claim, $this->staff('cs'), '0812 3456 7890');
    }

    public function test_approve_finance_ship_deliver_sampai_selesai(): void
    {
        $claim = $this->claim('hadiah_besar', 'perlu_finance');

        ClaimFlow::approveFinance($claim, $this->staff('finance'));
        $this->assertSame('diproses', $claim->fresh()->status);

        $cs = $this->staff('cs');
        ClaimFlow::ship($claim, $cs, 'JX-HADIAH-1');
        $this->assertSame('dikirim', $claim->fresh()->status);
        $this->assertSame('JX-HADIAH-1', $claim->fresh()->shipping_awb);

        ClaimFlow::deliver($claim, $cs);
        $this->assertSame('selesai', $claim->fresh()->status);
    }

    public function test_transisi_dari_status_salah_ditolak(): void
    {
        $claim = $this->claim();

        $this->expectException(ClaimTransitionException::class);
        ClaimFlow::ship($claim, $this->staff('cs'), 'JX-1');
    }

    public function test_expire_hadiah_katalog_mengembalikan_poin_dan_stok(): void
    {
        $customer = Customer::create(['phone' => '081200000099', 'brand' => $this->brand()]);
        $wallet = new PointWallet($customer);
        $wallet->grant(1000, 12);
        $catalog = RewardCatalog::create(['brand' => $this->brand(), 'period' => now()->format('Y-m'), 'published_at' => now()]);
        $item = $catalog->items()->create(['name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 5, 'stock_used' => 1]);

        $claim = ClaimService::createFromCatalog($customer, $item);
        $wallet->spend($claim, 500, 500);
        $this->assertSame(500, $wallet->balance());

        ClaimFlow::expire($claim);

        $this->assertSame('hangus', $claim->fresh()->status);
        $this->assertSame(1000, $wallet->balance());
        $this->assertSame(0, $item->fresh()->stock_used);
    }

    public function test_expire_hadiah_utama_mengembalikan_kuota(): void
    {
        $catalog = PrizeCatalog::create(['brand' => $this->brand(), 'name' => 'Emas uji', 'tier' => 'hadiah_besar', 'is_capped' => true, 'active' => true]);
        $this->alwaysPlaceBigPrize();
        DB::table('quota_caps')->insert([
            'brand' => $this->brand(), 'prize_name' => 'Emas uji', 'cap_units' => 1, 'used_units' => 1,
            'program_start' => now(), 'program_end' => now()->addMonth(),
        ]);
        $claim = $this->claim('hadiah_besar', 'perlu_finance');
        $claim->gamePrize->update(['prize_catalog_id' => $catalog->id]);

        ClaimFlow::expire($claim->fresh());

        $this->assertSame(0, (int) DB::table('quota_caps')->where('prize_name', 'Emas uji')->value('used_units'));
    }

    public function test_label_dan_hitungan_antrian(): void
    {
        $this->claim(status: 'diajukan');
        $this->claim(status: 'diajukan', phone: '081200000077');
        $this->claim(status: 'diproses', phone: '081200000078');

        $this->assertSame('Menunggu CS', ClaimFlow::label('diajukan'));
        $this->assertSame('Hadiah didapat', ClaimFlow::eventLabel(null, 'belum_redeem'));
        $counts = ClaimFlow::counts($this->brand());
        $this->assertSame(2, $counts['diajukan']);
        $this->assertSame(1, $counts['diproses']);
        $this->assertSame(0, $counts['perlu_finance']);
        $this->assertSame(0, ClaimEvent::where('to_status', 'diproses')->count());
    }
}
