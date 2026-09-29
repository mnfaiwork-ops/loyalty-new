<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use Tests\TestCase;

final class RewardCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): string
    {
        return (string) config('app.brand');
    }

    private function customerWith(int $points): Customer
    {
        $customer = Customer::create(['phone' => '0812'.random_int(10000000, 99999999), 'brand' => $this->brand()]);
        if ($points > 0) {
            (new PointWallet($customer))->grant($points, 12);
        }

        return $customer;
    }

    private function catalog(string $period, bool $published = true): RewardCatalog
    {
        return RewardCatalog::create([
            'brand' => $this->brand(),
            'period' => $period,
            'published_at' => $published ? now() : null,
        ]);
    }

    public function test_active_memakai_katalog_terbit_terbaru_yang_sudah_berjalan(): void
    {
        $this->catalog(now()->subMonth()->format('Y-m'));
        $current = $this->catalog(now()->format('Y-m'));
        $this->catalog(now()->addMonth()->format('Y-m'));

        $this->assertSame($current->id, RewardCatalogService::active($this->brand())->id);
    }

    public function test_bulan_baru_belum_terbit_katalog_lama_tetap_aktif(): void
    {
        $old = $this->catalog(now()->subMonth()->format('Y-m'));
        $this->catalog(now()->format('Y-m'), published: false);

        $this->assertSame($old->id, RewardCatalogService::active($this->brand())->id);
    }

    public function test_tukar_memotong_poin_stok_dan_membuat_klaim(): void
    {
        $customer = $this->customerWith(1200);
        $item = $this->catalog(now()->format('Y-m'))->items()->create(['name' => 'Voucher Free Ongkir', 'points_cost' => 1000, 'stock' => 3]);

        $claim = RewardCatalogService::exchange($customer, $item->id);

        $this->assertSame('belum_redeem', $claim->status);
        $this->assertSame('katalog', $claim->source);
        $this->assertSame('Voucher Free Ongkir', $claim->prize_name);
        $this->assertSame(1000, $claim->points_spent);
        $this->assertEqualsWithDelta(14, now()->diffInDays($claim->expires_at), 0.1);
        $this->assertSame(200, (new PointWallet($customer))->balance());
        $this->assertSame(1, $item->fresh()->stock_used);
    }

    public function test_tukar_kedua_saat_saldo_tidak_cukup_tidak_membuat_klaim(): void
    {
        $customer = $this->customerWith(1200);
        $item = $this->catalog(now()->format('Y-m'))->items()->create(['name' => 'Voucher Free Ongkir', 'points_cost' => 1000]);

        RewardCatalogService::exchange($customer, $item->id);

        try {
            RewardCatalogService::exchange($customer, $item->id);
            $this->fail('tukar kedua harus ditolak');
        } catch (InsufficientPointsException $e) {
            $this->assertSame(200, $e->balance);
        }

        $this->assertSame(1, Claim::count());
        $this->assertSame(200, (new PointWallet($customer))->balance());
    }

    public function test_item_nonaktif_atau_stok_habis_ditolak_tanpa_memotong_poin(): void
    {
        $customer = $this->customerWith(2000);
        $catalog = $this->catalog(now()->format('Y-m'));
        $off = $catalog->items()->create(['name' => 'Nonaktif', 'points_cost' => 500, 'active' => false]);
        $empty = $catalog->items()->create(['name' => 'Habis', 'points_cost' => 500, 'stock' => 1, 'stock_used' => 1]);

        foreach ([$off, $empty] as $item) {
            try {
                RewardCatalogService::exchange($customer, $item->id);
                $this->fail("{$item->name} harus ditolak");
            } catch (CatalogUnavailableException) {
                // diharapkan
            }
        }

        $this->assertSame(0, Claim::count());
        $this->assertSame(2000, (new PointWallet($customer))->balance());
    }

    public function test_item_dari_katalog_yang_tidak_aktif_ditolak(): void
    {
        $customer = $this->customerWith(2000);
        $this->catalog(now()->format('Y-m'));
        $future = $this->catalog(now()->addMonth()->format('Y-m'))->items()->create(['name' => 'Bulan depan', 'points_cost' => 500]);

        $this->expectException(CatalogUnavailableException::class);
        RewardCatalogService::exchange($customer, $future->id);
    }

    public function test_salin_dan_terbitkan_katalog(): void
    {
        $from = now()->subMonth()->format('Y-m');
        $to = now()->format('Y-m');
        $source = $this->catalog($from);
        $source->items()->create(['name' => 'Pouch Exclusive', 'points_cost' => 500, 'stock' => 10, 'stock_used' => 7, 'sort' => 1]);
        $source->items()->create(['name' => 'Exclusive ToteBag', 'points_cost' => 700, 'sort' => 2]);

        $this->assertSame($from, RewardCatalogService::previousPeriod($to));
        $this->assertSame(2, RewardCatalogService::copyFrom($this->brand(), $from, $to));

        $copy = RewardCatalogService::forPeriod($this->brand(), $to);
        $this->assertNull($copy->published_at);
        $this->assertSame(['Pouch Exclusive', 'Exclusive ToteBag'], $copy->items->pluck('name')->all());
        $this->assertSame(0, $copy->items->first()->stock_used);

        RewardCatalogService::publish($this->brand(), $to);
        $this->assertSame($copy->id, RewardCatalogService::active($this->brand())->id);

        $this->expectException(CatalogUnavailableException::class);
        RewardCatalogService::copyFrom($this->brand(), $from, $to);
    }
}
