<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\FairtrackSqliteOrderStatusSource;
use PHPUnit\Framework\TestCase;

final class FairtrackSqliteOrderStatusSourceTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = tempnam(sys_get_temp_dir(), 'ft').'.db';
        $pdo = new \PDO('sqlite:'.$this->db);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('LNCH1','628111','completed','complete')");
        $pdo->exec("insert into orders values ('LNCH2','628222','sent','problem')");
    }

    protected function tearDown(): void
    {
        @unlink($this->db);
        parent::tearDown();
    }

    public function test_membaca_order_complete(): void
    {
        $src = new FairtrackSqliteOrderStatusSource($this->db);
        $status = $src->find('LNCH1');

        $this->assertNotNull($status);
        $this->assertSame('complete', $status->statusFulfillment);
        $this->assertSame('628111', $status->phone);
        $this->assertTrue($status->isDelivered());
    }

    public function test_order_problem_ditolak(): void
    {
        $src = new FairtrackSqliteOrderStatusSource($this->db);

        $this->assertFalse($src->find('LNCH2')->isDelivered());
    }

    public function test_awb_tak_ada_return_null(): void
    {
        $src = new FairtrackSqliteOrderStatusSource($this->db);

        $this->assertNull($src->find('TIDAKADA'));
    }

    public function test_db_tidak_ada_return_null_fail_closed(): void
    {
        $src = new FairtrackSqliteOrderStatusSource('/tmp/tidak-ada-'.$this->db);

        $this->assertNull($src->find('LNCH1'));
    }

    public function test_awb_kosong_return_null(): void
    {
        $src = new FairtrackSqliteOrderStatusSource($this->db);

        $this->assertNull($src->find(''));
    }
}
