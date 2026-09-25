<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\MarkomHttpOrderStatusSource;
use Tests\TestCase;

/**
 * MarkomHttpOrderStatusSource + MarkomApiOrderGate dengan Http::fake.
 * Kontrak: GET {base}/orders/{awb} → {status_order, status_fulfillment, customer_phone}.
 */
class MarkomGateTest extends TestCase
{
    private function source(): MarkomHttpOrderStatusSource
    {
        return new MarkomHttpOrderStatusSource('https://markom.test/api', 'rahasia', 5);
    }

    public function test_order_complete_dan_hp_cocok_lolos(): void
    {
        Http::fake([
            'markom.test/api/orders/LNCH1' => Http::response([
                'status_order' => 'completed',
                'status_fulfillment' => 'complete',
                'customer_phone' => '628123456789',
            ]),
        ]);

        $gate = new MarkomApiOrderGate($this->source());
        $result = $gate->isDelivered('LNCH1', '08123456789');

        $this->assertTrue($result->delivered);

        Http::assertSent(fn ($req) => $req->url() === 'https://markom.test/api/orders/LNCH1'
            && $req->hasHeader('Authorization', 'Bearer rahasia'));
    }

    public function test_sent_problem_ditolak(): void
    {
        Http::fake([
            'markom.test/api/orders/LNCH2' => Http::response([
                'status_order' => 'sent',
                'status_fulfillment' => 'problem',
                'customer_phone' => '628123456789',
            ]),
        ]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('LNCH2', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Paket belum diterima', $result->reason);
    }

    public function test_awb_curian_hp_beda_ditolak(): void
    {
        Http::fake([
            'markom.test/api/orders/LNCH3' => Http::response([
                'status_order' => 'completed',
                'status_fulfillment' => 'complete',
                'customer_phone' => '628999999999',
            ]),
        ]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('LNCH3', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('AWB tidak cocok dengan nomor HP', $result->reason);
    }

    public function test_404_order_tidak_ditemukan(): void
    {
        Http::fake(['markom.test/*' => Http::response([], 404)]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('NOPE', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Order tidak ditemukan', $result->reason);
    }

    public function test_error_server_ditolak_fail_closed(): void
    {
        Http::fake(['markom.test/*' => Http::response('boom', 500)]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('LNCH1', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Order tidak ditemukan', $result->reason);
    }

    public function test_timeout_ditolak_fail_closed(): void
    {
        Http::fake(['markom.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);

        $result = (new MarkomApiOrderGate($this->source()))->isDelivered('LNCH1', '08123456789');

        $this->assertFalse($result->delivered);
    }

    public function test_base_url_kosong_tidak_memanggil_apa_apa(): void
    {
        Http::fake();

        $result = (new MarkomApiOrderGate(new MarkomHttpOrderStatusSource('')))->isDelivered('LNCH1', '08123456789');

        $this->assertFalse($result->delivered);
        Http::assertNothingSent();
    }
}
