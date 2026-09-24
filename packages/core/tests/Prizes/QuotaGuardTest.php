<?php

namespace LoyaltyNew\Core\Tests\Prizes;

use LoyaltyNew\Core\Prizes\QuotaGuard;
use PHPUnit\Framework\TestCase;

final class QuotaGuardTest extends TestCase
{
    public function test_emas_cap_1_unit(): void
    {
        $this->assertTrue(QuotaGuard::canAward(capUnits: 1, usedUnits: 0));
        $this->assertFalse(QuotaGuard::canAward(capUnits: 1, usedUnits: 1));
    }

    public function test_philips_cap_2_unit(): void
    {
        $this->assertTrue(QuotaGuard::canAward(capUnits: 2, usedUnits: 1));
        $this->assertFalse(QuotaGuard::canAward(capUnits: 2, usedUnits: 2));
    }

    public function test_remaining_tidak_negatif(): void
    {
        $this->assertSame(0, QuotaGuard::remaining(1, 1));
        $this->assertSame(1, QuotaGuard::remaining(2, 1));
        $this->assertSame(0, QuotaGuard::remaining(2, 5));
    }
}
