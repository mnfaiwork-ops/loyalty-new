<?php

namespace LoyaltyNew\Core\Tests\Gate;

use LoyaltyNew\Core\Gate\AwbMatcher;
use PHPUnit\Framework\TestCase;

final class AwbMatcherTest extends TestCase
{
    public function test_awb_dan_hp_cocok(): void
    {
        $this->assertTrue(AwbMatcher::matches('JX123', '08123456789', 'JX123', '08123456789'));
    }

    public function test_awb_benar_hp_salah_ditolak(): void
    {
        $this->assertFalse(AwbMatcher::matches('JX123', '08999999999', 'JX123', '08123456789'));
    }

    public function test_hp_dinormalisasi(): void
    {
        $this->assertTrue(AwbMatcher::matches('JX123', '+628123456789', 'JX123', '08123456789'));
        $this->assertTrue(AwbMatcher::matches('JX123', '628123456789', 'JX123', '0812 3456 789'));
    }

    public function test_awb_kosong_ditolak(): void
    {
        $this->assertFalse(AwbMatcher::matches('', '08123456789', '', '08123456789'));
    }

    public function test_hp_kosong_ditolak(): void
    {
        $this->assertFalse(AwbMatcher::matches('JX123', '', 'JX123', ''));
    }

    public function test_hp_non_numerik_berbeda_tidak_cocok(): void
    {
        $this->assertFalse(AwbMatcher::matches('abc', '---', 'abc', 'xyz'));
    }

    public function test_awb_order_kosong_ditolak(): void
    {
        $this->assertFalse(AwbMatcher::matches('JX123', '08123456789', '', '08123456789'));
    }

    public function test_normalize_phone_publik(): void
    {
        $this->assertSame('08123456789', AwbMatcher::normalizePhone('+62 812-3456-789'));
        $this->assertSame('08123456789', AwbMatcher::normalizePhone('08123456789'));
    }
}
