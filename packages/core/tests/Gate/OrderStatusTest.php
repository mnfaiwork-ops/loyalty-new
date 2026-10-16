<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function test_complete_selalu_lolos(): void
    {
        $this->assertTrue((new OrderStatus('A', '08', 'sent', 'complete'))->isDelivered());
        $this->assertTrue((new OrderStatus('A', '08', 'completed', 'complete'))->isDelivered());
        $this->assertTrue((new OrderStatus('A', '08', null, 'complete'))->isDelivered());
    }

    public function test_sent_problem_ditolak(): void
    {
        // Data live: 491 order sent+problem (gagal antar) → TIDAK boleh lolos.
        $this->assertFalse((new OrderStatus('A', '08', 'sent', 'problem'))->isDelivered());
    }

    public function test_sent_doang_ditolak(): void
    {
        // status_order='sent' saja tidak cukup — penentu status_fulfillment.
        $this->assertFalse((new OrderStatus('A', '08', 'sent', 'sent'))->isDelivered());
    }

    public function test_status_order_completed_diterima_sebagai_padanan(): void
    {
        $this->assertTrue((new OrderStatus('A', '08', 'completed', null))->isDelivered());
    }

    public function test_orders_status_completed_wms_lolos(): void
    {
        // WMS: orders.status memakai 'completed', bukan 'complete'.
        $this->assertTrue((new OrderStatus('A', '08', 'sent', 'completed'))->isDelivered());
    }

    public function test_retur_dan_batal_ditolak_walau_status_completed(): void
    {
        foreach (['request_return', 'returned', 'request_cancel', 'cancelled'] as $fulfillment) {
            $this->assertFalse((new OrderStatus('A', '08', 'completed', $fulfillment))->isDelivered(), $fulfillment);
        }
        $this->assertFalse((new OrderStatus('A', '08', 'returned', null))->isDelivered());
    }

    public function test_status_lain_ditolak(): void
    {
        $this->assertFalse((new OrderStatus('A', '08', 'process', 'packed'))->isDelivered());
        $this->assertFalse((new OrderStatus('A', '08', null, null))->isDelivered());
        $this->assertFalse((new OrderStatus('A', '08', 'cancelled', 'cancelled'))->isDelivered());
    }
}
