<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\FairtrackSqliteOrderStatusSource;
use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderStatus;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use PHPUnit\Framework\TestCase;

final class CustomerNameTest extends TestCase
{
    public function test_fairtrack_membaca_customer_name(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'ftn').'.db';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec('create table orders (awb text, customer_phone text, customer_name text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('LNCHN','628111','Sari Wulandari','completed','complete')");

        $status = (new FairtrackSqliteOrderStatusSource($db))->find('LNCHN');
        @unlink($db);

        $this->assertSame('Sari Wulandari', $status->customerName);
    }

    public function test_fairtrack_tanpa_kolom_nama_tetap_jalan(): void
    {
        $db = tempnam(sys_get_temp_dir(), 'ftx').'.db';
        $pdo = new \PDO('sqlite:'.$db);
        $pdo->exec('create table orders (awb text, customer_phone text, status_order text, status_fulfillment text)');
        $pdo->exec("insert into orders values ('LNCHX','628111','completed','complete')");

        $status = (new FairtrackSqliteOrderStatusSource($db))->find('LNCHX');
        @unlink($db);

        $this->assertNotNull($status);
        $this->assertNull($status->customerName);
    }

    public function test_gate_meneruskan_nama_saat_lolos(): void
    {
        $source = new class implements OrderStatusSource {
            public function find(string $awb): ?OrderStatus
            {
                return new OrderStatus('JX1', '628123456789', 'completed', 'complete', 'Sari');
            }
        };

        $result = (new MarkomApiOrderGate($source))->isDelivered('JX1', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertSame('Sari', $result->customerName);
    }
}
