<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use LoyaltyNew\Core\Gate\CachedOrderStatusSource;
use Tests\TestCase;

/**
 * Webhook Markom: HMAC wajib, bust cache AWB (C2).
 */
class MarkomWebhookTest extends TestCase
{
    private const SECRET = 'rahasia-webhook';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.brand' => 'reglow', 'markom.webhook_secret' => self::SECRET]);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::SECRET);
    }

    public function test_tanda_tangan_valid_membuang_cache(): void
    {
        Cache::put('markom:order:reglow:LNCH1', ['awb' => 'LNCH1'], 60);
        $this->assertNotNull(Cache::get('markom:order:reglow:LNCH1'));

        $body = json_encode(['awb' => 'LNCH1', 'status_fulfillment' => 'complete']);
        $res = $this->call('POST', '/api/webhooks/markom', [], [], [], [
            'HTTP_X-MARKOM-SIGNATURE' => $this->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $res->assertOk()->assertJson(['ok' => true]);
        $this->assertNull(Cache::get('markom:order:reglow:LNCH1'), 'cache harus dibuang');
    }

    public function test_tanda_tangan_salah_ditolak(): void
    {
        $body = json_encode(['awb' => 'LNCH1']);
        $res = $this->call('POST', '/api/webhooks/markom', [], [], [], [
            'HTTP_X-MARKOM-SIGNATURE' => 'salah',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $res->assertStatus(401);
    }

    public function test_tanpa_secret_ditolak_fail_closed(): void
    {
        config(['markom.webhook_secret' => null]);
        $body = json_encode(['awb' => 'LNCH1']);

        $res = $this->call('POST', '/api/webhooks/markom', [], [], [], [
            'HTTP_X-MARKOM-SIGNATURE' => hash_hmac('sha256', $body, 'apa-saja'),
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $res->assertStatus(401);
    }

    public function test_awb_wajib_ada(): void
    {
        $body = json_encode(['status_fulfillment' => 'complete']);
        $res = $this->call('POST', '/api/webhooks/markom', [], [], [], [
            'HTTP_X-MARKOM-SIGNATURE' => $this->sign($body),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);

        $res->assertStatus(422);
    }

    public function test_brand_lain_tidak_ikut_dibuang(): void
    {
        Cache::put('markom:order:amura:LNCH1', ['awb' => 'LNCH1'], 60);

        $body = json_encode(['awb' => 'LNCH1']);
        $this->call('POST', '/api/webhooks/markom', [], [], [], [
            'HTTP_X-MARKOM-SIGNATURE' => $this->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertNotNull(Cache::get('markom:order:amura:LNCH1'), 'cache brand lain tidak boleh tersentuh');
    }
}
