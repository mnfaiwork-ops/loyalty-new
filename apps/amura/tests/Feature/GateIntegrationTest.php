<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoyaltyNew\Core\Gate\OrderGate;
use LoyaltyNew\Core\Models\GameSession;
use Tests\TestCase;

/**
 * Bukti spec §9 cek #1: gate nyata menolak/meloloskan berdasarkan
 * status_fulfillment='complete' + cocok AWB dengan HP, lewat ServiceService
 * yang sesungguhnya (bukan stub), memakai sumber SQLite sementara.
 */
class GateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $ftDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ftDb = tempnam(sys_get_temp_dir(), 'ft').'.db';
        $pdo = new \PDO('sqlite:'.$this->ftDb);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        // 133-rule: sent+complete LOLOS.
        $pdo->exec("insert into orders values ('LNCH-OK','628111222333','sent','complete')");
        // 491-rule: sent+problem DITOLAK.
        $pdo->exec("insert into orders values ('LNCH-PROBLEM','628111222333','sent','problem')");

        config([
            'app.brand' => 'amura',
            'markom.gate' => 'markom',
            'markom.driver' => 'fairtrack_sqlite',
            'markom.fairtrack.database' => $this->ftDb,
        ]);
        app()->forgetInstance(OrderGate::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->ftDb);
        parent::tearDown();
    }

    public function test_paket_complete_dan_hp_cocok_bisa_mulai_sesi(): void
    {
        $res = $this->post(route('play.start'), [
            'awb' => 'LNCH-OK',
            'phone' => '08111222333',
        ]);

        $res->assertRedirect();
        $this->assertTrue(GameSession::withoutGlobalScopes()->where('awb', 'LNCH-OK')->exists());

        // gate_source mencatat implementasi nyata (bukan stub).
        $this->assertSame('markom', GameSession::withoutGlobalScopes()->where('awb', 'LNCH-OK')->value('gate_source'));
    }

    public function test_paket_problem_ditolak(): void
    {
        $res = $this->post(route('play.start'), [
            'awb' => 'LNCH-PROBLEM',
            'phone' => '08111222333',
        ]);

        $res->assertSessionHasErrors();
        $this->assertFalse(GameSession::withoutGlobalScopes()->where('awb', 'LNCH-PROBLEM')->exists());
    }

    public function test_hp_salah_ditolak_walau_paket_complete(): void
    {
        $res = $this->post(route('play.start'), [
            'awb' => 'LNCH-OK',
            'phone' => '08999999999',
        ]);

        $res->assertSessionHasErrors();
        $this->assertFalse(GameSession::withoutGlobalScopes()->where('awb', 'LNCH-OK')->exists());
    }

    public function test_order_tidak_ada_ditolak(): void
    {
        $res = $this->post(route('play.start'), [
            'awb' => 'TIDAK-ADA',
            'phone' => '08111222333',
        ]);

        $res->assertSessionHasErrors();
    }
}
