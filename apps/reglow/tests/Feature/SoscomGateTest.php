<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use LoyaltyNew\Core\Gate\SoscomOrderStatusSource;
use Tests\TestCase;

/**
 * SoscomOrderStatusSource + MarkomApiOrderGate dengan Http::fake.
 * Kontrak: docs/api/soscom-open-api.md (GET orders/detail?phone=&awb=, X-Api-Key).
 */
class SoscomGateTest extends TestCase
{
    private const URL = 'https://wms.test/v1/open/social-commerce/orders/detail*';

    private function source(array $fields = [], ?string $key = 'key-reglow'): SoscomOrderStatusSource
    {
        return new SoscomOrderStatusSource('https://wms.test', $key, 5, $fields);
    }

    private function order(array $override = []): array
    {
        return ['code' => 200, 'msg' => ['id' => 'Sukses', 'en' => 'Success'], 'data' => array_replace_recursive([
            'id' => 129005,
            'status' => 'completed',
            'awb' => 'C1MACRQU',
            'customer' => ['id' => 1, 'name' => 'Budi'],
            'customer_shipping' => ['name' => 'Budi', 'phone' => '628123456789'],
            'orders' => ['status' => 'completed', 'awb' => 'C1MACRQU'],
        ], $override)];
    }

    public function test_order_completed_dan_hp_cocok_lolos(): void
    {
        Http::fake([self::URL => Http::response($this->order())]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertSame('Budi', $result->customerName);

        Http::assertSent(fn (Request $req) => $req->hasHeader('X-Api-Key', 'key-reglow')
            && $req['phone'] === '08123456789'
            && $req['awb'] === 'C1MACRQU');
    }

    public function test_hp_dengan_spasi_dan_strip_dikirim_sebagai_angka(): void
    {
        Http::fake([self::URL => Http::response($this->order())]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '+62 812-3456-789');

        $this->assertTrue($result->delivered);
        Http::assertSent(fn (Request $req) => $req['phone'] === '08123456789');
    }

    public function test_order_masih_dikirim_ditolak(): void
    {
        Http::fake([self::URL => Http::response($this->order(['status' => 'sent', 'orders' => ['status' => 'sent']]))]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Paket belum diterima', $result->reason);
    }

    public function test_order_completed_tapi_diretur_gudang_ditolak(): void
    {
        Http::fake([self::URL => Http::response($this->order(['orders' => ['status' => 'request_return']]))]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Paket belum diterima', $result->reason);
    }

    public function test_404_hp_dan_awb_tidak_cocok_ditolak(): void
    {
        Http::fake([self::URL => Http::response(['code' => 404], 404)]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '08999999999');

        $this->assertFalse($result->delivered);
        $this->assertSame('Order tidak ditemukan', $result->reason);
    }

    public function test_key_salah_ditolak(): void
    {
        Http::fake([self::URL => Http::response(['code' => 403], 403)]);

        $this->assertNull($this->source()->find('C1MACRQU', '08123456789'));
    }

    public function test_tanpa_key_tidak_memanggil_api(): void
    {
        Http::fake();

        $this->assertNull($this->source([], null)->find('C1MACRQU', '08123456789'));
        Http::assertNothingSent();
    }

    public function test_hp_order_beda_dengan_hp_input_ditolak(): void
    {
        Http::fake([self::URL => Http::response($this->order(['customer_shipping' => ['phone' => '628999999999']]))]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('C1MACRQU', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('AWB tidak cocok dengan nomor HP', $result->reason);
    }

    public function test_path_field_bisa_diganti_lewat_config(): void
    {
        Http::fake([self::URL => Http::response($this->order([
            'status' => 'completed',
            'orders' => ['status' => 'complete'],
        ]))]);

        $status = $this->source(['status_order' => '', 'status_fulfillment' => 'orders.status'])
            ->find('C1MACRQU', '08123456789');

        $this->assertNull($status->statusOrder);
        $this->assertSame('complete', $status->statusFulfillment);
        $this->assertTrue($status->isDelivered());
    }

    public function test_driver_soscom_api_memakai_sumber_soscom(): void
    {
        config([
            'markom.driver' => 'soscom_api',
            'markom.cache_ttl' => 0,
            'markom.soscom.base_url' => 'https://wms.test',
            'markom.soscom.api_key' => 'key-reglow',
        ]);

        $this->assertInstanceOf(SoscomOrderStatusSource::class, app(OrderStatusSource::class));
    }
}
