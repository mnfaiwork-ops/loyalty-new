<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use LoyaltyNew\Core\Gate\CachedOrderStatusSource;
use LoyaltyNew\Core\Gate\OrderStatus;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use Tests\TestCase;

/**
 * Cache status order + bust lewat webhook (C2: kesegaran status).
 */
class OrderStatusCacheTest extends TestCase
{
    private function countingSource(OrderStatus $status, int &$calls): OrderStatusSource
    {
        return new class($status, $calls) implements OrderStatusSource
        {
            public function __construct(private OrderStatus $status, private int &$calls)
            {
            }

            public function find(string $awb, string $phone = ''): ?OrderStatus
            {
                $this->calls++;

                return strtoupper($awb) === strtoupper($this->status->awb) ? $this->status : null;
            }
        };
    }

    public function test_hasil_dicache_sehingga_sumber_hanya_dipanggil_sekali(): void
    {
        $calls = 0;
        $src = new CachedOrderStatusSource(
            $this->countingSource(new OrderStatus('LNCH1', '628111', 'completed', 'complete'), $calls),
            60,
            'reglow',
        );

        $this->assertTrue($src->find('LNCH1')->isDelivered());
        $this->assertTrue($src->find('LNCH1')->isDelivered());
        $this->assertSame(1, $calls, 'sumber harus dipanggil sekali saja (sisanya cache)');
    }

    public function test_forget_membuat_sumber_dipanggil_lagi(): void
    {
        $calls = 0;
        $src = new CachedOrderStatusSource(
            $this->countingSource(new OrderStatus('LNCH1', '628111', 'completed', 'complete'), $calls),
            60,
            'reglow',
        );

        $src->find('LNCH1');
        CachedOrderStatusSource::forget('reglow', 'LNCH1');
        $src->find('LNCH1');

        $this->assertSame(2, $calls);
    }

    public function test_hasil_null_tidak_dicache(): void
    {
        $calls = 0;
        $src = new CachedOrderStatusSource(
            $this->countingSource(new OrderStatus('LNCH1', '628111', 'completed', 'complete'), $calls),
            60,
            'reglow',
        );

        $this->assertNull($src->find('TIDAK-ADA'));
        $this->assertNull($src->find('TIDAK-ADA'));
        $this->assertSame(2, $calls, 'order yang tidak ada di-query ulang (tidak dicache negatif)');
    }

    public function test_cache_terpisah_per_brand(): void
    {
        $calls = 0;
        $status = new OrderStatus('LNCH1', '628111', 'completed', 'complete');

        (new CachedOrderStatusSource($this->countingSource($status, $calls), 60, 'reglow'))->find('LNCH1');
        (new CachedOrderStatusSource($this->countingSource($status, $calls), 60, 'amura'))->find('LNCH1');

        $this->assertSame(2, $calls, 'cache amura dan reglow harus terpisah');
    }
}
