<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use Tests\TestCase;

/** Server demo untuk tim Markom (resi dummy, permintaan Fai 2026-10-23). */
final class DemoServerTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = tempnam(sys_get_temp_dir(), 'demo').'.csv';
        file_put_contents($this->csv, implode("\n", [
            'awb,phone,customer_name,order_amount,ordered_at,status_order,status_fulfillment,catatan',
            'DEMOAMR001,089990000001,Ayu Lestari,360000,2026-11-01,completed,completed,pelanggan baru',
        ])."\n");

        config([
            'markom.gate' => 'markom',
            'markom.driver' => 'demo',
            'markom.cache_ttl' => 0,
            'markom.demo.file' => $this->csv,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function test_resi_dummy_bisa_main(): void
    {
        $this->post('/play/start', ['awb' => 'DEMOAMR001', 'phone' => '899 9000 0001'])
            ->assertRedirect(route('pin.create'));
    }

    public function test_resi_dummy_dengan_hp_lain_ditolak(): void
    {
        $this->post('/play/start', ['awb' => 'DEMOAMR001', 'phone' => '089990000002'])
            ->assertSessionHasErrors('awb');
    }

    public function test_driver_demo_ditolak_di_produksi(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('MARKOM_DRIVER=demo tidak boleh dipakai saat APP_ENV=production.');
            app(OrderStatusSource::class);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_demo_setup_menolak_selain_server_demo(): void
    {
        config(['markom.driver' => 'soscom_api']);

        $this->artisan('loyalty:demo-setup')->assertFailed();
    }
}
