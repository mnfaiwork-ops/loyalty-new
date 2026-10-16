<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\MarkomApiOrderGate;
use LoyaltyNew\Core\Gate\OrderStatus;
use LoyaltyNew\Core\Gate\OrderStatusSource;
use PHPUnit\Framework\TestCase;

final class MarkomApiOrderGateTest extends TestCase
{
    private function gate(?OrderStatus $status): MarkomApiOrderGate
    {
        $source = new class($status) implements OrderStatusSource
        {
            public function __construct(private ?OrderStatus $status)
            {
            }

            public function find(string $awb, string $phone = ''): ?OrderStatus
            {
                return $this->status;
            }
        };

        return new MarkomApiOrderGate($source);
    }

    public function test_order_complete_dan_hp_cocok_lolos(): void
    {
        $gate = $this->gate(new OrderStatus('JX1', '628123456789', 'completed', 'complete'));

        // HP dinormalisasi: 62… vs 0… tetap cocok.
        $result = $gate->isDelivered('JX1', '08123456789');

        $this->assertTrue($result->delivered);
    }

    public function test_order_tak_ditemukan_ditolak(): void
    {
        $result = $this->gate(null)->isDelivered('JX1', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Resi atau nomor HP tidak cocok', $result->reason);
    }

    public function test_paket_belum_diterima_ditolak(): void
    {
        $gate = $this->gate(new OrderStatus('JX1', '628123456789', 'sent', 'problem'));
        $result = $gate->isDelivered('JX1', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Paket belum diterima', $result->reason);
    }

    public function test_awb_curian_hp_beda_ditolak(): void
    {
        $gate = $this->gate(new OrderStatus('JX1', '628999999999', 'completed', 'complete'));
        $result = $gate->isDelivered('JX1', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Resi atau nomor HP tidak cocok', $result->reason);
    }

    public function test_hp_beda_pada_paket_belum_diterima_tidak_membocorkan_status(): void
    {
        // Yang tidak tahu HP pemilik tidak boleh tahu resi ini ada dan belum sampai.
        $gate = $this->gate(new OrderStatus('JX1', '628999999999', 'sent', 'problem'));
        $result = $gate->isDelivered('JX1', '08123456789');

        $this->assertFalse($result->delivered);
        $this->assertSame('Resi atau nomor HP tidak cocok', $result->reason);
    }

    public function test_awb_atau_hp_kosong_ditolak(): void
    {
        $gate = $this->gate(new OrderStatus('JX1', '628123456789', 'completed', 'complete'));

        $this->assertSame('AWB kosong', $gate->isDelivered('', '08123456789')->reason);
        $this->assertSame('Nomor HP kosong', $gate->isDelivered('JX1', '')->reason);
    }

    public function test_hp_order_kosong_ditolak_fail_closed(): void
    {
        // Sumber punya order complete tapi tanpa HP → tidak bisa dicocokkan → tolak.
        $gate = $this->gate(new OrderStatus('JX1', null, 'completed', 'complete'));
        $result = $gate->isDelivered('JX1', '08123456789');

        $this->assertFalse($result->delivered);
    }

    public function test_gate_meneruskan_hp_ke_sumber(): void
    {
        $source = new class implements OrderStatusSource
        {
            public string $phone = '';

            public function find(string $awb, string $phone = ''): ?OrderStatus
            {
                $this->phone = $phone;

                return null;
            }
        };

        (new MarkomApiOrderGate($source))->isDelivered('JX1', '08123456789');

        $this->assertSame('08123456789', $source->phone);
    }
}
