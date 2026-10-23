<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\DemoOrderStatusSource;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use PHPUnit\Framework\TestCase;

final class DemoOrderStatusSourceTest extends TestCase
{
    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = tempnam(sys_get_temp_dir(), 'demo').'.csv';
        file_put_contents($this->csv, implode("\n", [
            'awb,phone,customer_name,order_amount,ordered_at,status_order,status_fulfillment,catatan',
            'DEMOAMR001,089990000001,Ayu Lestari,360000,2026-11-01,completed,completed,"pelanggan baru, satu paket"',
            'DEMOAMR901,089990000041,Ira Setiawati,150000,2026-10-29,sent,on_delivery,GAGAL: paket belum diterima',
        ])."\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function test_membaca_order_dummy(): void
    {
        $status = (new DemoOrderStatusSource($this->csv))->find('demoamr001');

        $this->assertNotNull($status);
        $this->assertSame('089990000001', $status->phone);
        $this->assertSame('Ayu Lestari', $status->customerName);
        $this->assertSame(360000, $status->orderAmount);
        $this->assertSame('2026-11-01', $status->orderedAt);
        $this->assertTrue($status->isDelivered());
    }

    public function test_awb_tak_ada_return_null(): void
    {
        $this->assertNull((new DemoOrderStatusSource($this->csv))->find('DEMOAMR999'));
    }

    public function test_file_tidak_ada_return_null_fail_closed(): void
    {
        $this->assertNull((new DemoOrderStatusSource('/tmp/tidak-ada-demo.csv'))->find('DEMOAMR001'));
        $this->assertNull((new DemoOrderStatusSource(''))->find('DEMOAMR001'));
    }

    public function test_gate_asli_dengan_data_demo(): void
    {
        $gate = new MarkomApiOrderGate(new DemoOrderStatusSource($this->csv));

        $this->assertTrue($gate->isDelivered('DEMOAMR001', '+62 899 9000 0001')->delivered);
        $this->assertSame(MarkomApiOrderGate::NOT_MATCHED, $gate->isDelivered('DEMOAMR001', '089990000002')->reason);
        $this->assertSame('Paket belum diterima', $gate->isDelivered('DEMOAMR901', '089990000041')->reason);
    }
}
