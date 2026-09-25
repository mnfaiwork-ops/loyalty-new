<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Gate\OrderGate;
use Tests\TestCase;

/**
 * Kunci akses 15 menit setelah 5x salah (spec §7).
 */
class LockoutTest extends TestCase
{
    use RefreshDatabase;

    private string $ftDb;

    protected function setUp(): void
    {
        parent::setUp();

        // Gate nyata + sumber SQLite: supaya "salah" benar-benar berarti gate menolak.
        $this->ftDb = tempnam(sys_get_temp_dir(), 'ft').'.db';
        $pdo = new \PDO('sqlite:'.$this->ftDb);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('OK-1','628111222333','completed','complete')");

        config([
            'app.brand' => 'reglow',
            'markom.gate' => 'markom',
            'markom.driver' => 'fairtrack_sqlite',
            'markom.fairtrack.database' => $this->ftDb,
            'loyalty.lockout.max_fails' => 5,
            'loyalty.lockout.minutes' => 15,
        ]);
        app()->forgetInstance(OrderGate::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->ftDb);
        parent::tearDown();
    }

    private function wrong(): void
    {
        $this->post(route('play.start'), ['awb' => 'SALAH', 'phone' => '08111222333']);
    }

    public function test_lima_kali_salah_mengunci(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->wrong();
        }

        // Percobaan ke-6 dengan HP yang sama ditolak (kunci per HP), walau order benar.
        $res = $this->post(route('play.start'), ['awb' => 'OK-1', 'phone' => '08111222333']);
        $res->assertSessionHasErrors('awb');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('awb'));
    }

    public function test_lima_awb_berbeda_dengan_hp_sama_tetap_mengunci(): void
    {
        // Sumbu phone: uji satu HP ke banyak AWB harus tetap tertangkap.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('play.start'), ['awb' => "SALAH-{$i}", 'phone' => '08111222333']);
        }

        $res = $this->post(route('play.start'), ['awb' => 'OK-1', 'phone' => '08111222333']);
        $res->assertSessionHasErrors('awb');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('awb'));
    }

    public function test_kurang_dari_lima_belum_dikunci(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->wrong();
        }

        // Ke-5 masih boleh coba order benar → lolos.
        $res = $this->post(route('play.start'), ['awb' => 'OK-1', 'phone' => '08111222333']);
        $res->assertRedirect();
    }

    public function test_berhasil_reset_counter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->wrong();
        }
        // Berhasil → reset.
        $this->post(route('play.start'), ['awb' => 'OK-1', 'phone' => '08111222333'])->assertRedirect();

        // 2x salah lagi (total seharusnya 2, bukan 5) → belum terkunci.
        $this->wrong();
        $this->wrong();
        $this->post(route('play.start'), ['awb' => 'OK-1', 'phone' => '08111222333'])->assertRedirect();
    }

    public function test_hp_tidak_disimpan_mentah(): void
    {
        $this->wrong();

        $key = DB::table('play_attempts')->value('key');
        $this->assertNotNull($key);
        $this->assertStringNotContainsString('08111222333', (string) $key);
        $this->assertStringNotContainsString('628111222333', (string) $key);
    }

    public function test_brand_lain_tidak_terpengaruh(): void
    {
        // Kunci di reglow.
        for ($i = 0; $i < 5; $i++) {
            $this->wrong();
        }

        // Key amura dengan AWB+HP sama tetap bersih.
        $limiter = AttemptLimiter::fromConfig('amura');
        $this->assertNull($limiter->lockedFor('SALAH', '08111222333'));
    }

    public function test_limiter_locked_for_kosong_awal(): void
    {
        $limiter = AttemptLimiter::fromConfig('reglow');

        $this->assertNull($limiter->lockedFor('BELUM-ADA', '081200000000'));
    }

    public function test_record_failure_dan_clear(): void
    {
        $limiter = AttemptLimiter::fromConfig('reglow');

        $this->assertFalse($limiter->recordFailure('A', '0812'));
        $limiter->clear('A', '0812');
        $this->assertNull($limiter->lockedFor('A', '0812'));
    }
}
