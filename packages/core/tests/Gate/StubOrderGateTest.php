<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\StubOrderGate;
use PHPUnit\Framework\TestCase;

final class StubOrderGateTest extends TestCase
{
    public function test_awb_dan_hp_terisi_lolos(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('JX123', '08123456789');

        $this->assertTrue($result->delivered);
        $this->assertNull($result->reason);
    }

    public function test_awb_kosong_ditolak(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('AWB kosong', $result->reason);
    }

    public function test_hp_kosong_ditolak(): void
    {
        $gate = new StubOrderGate();
        $result = $gate->isDelivered('JX123', '');

        $this->assertFalse($result->delivered);
        $this->assertSame('Nomor HP kosong', $result->reason);
    }
}
