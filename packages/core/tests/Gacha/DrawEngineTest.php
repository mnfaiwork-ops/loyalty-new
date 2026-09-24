<?php

namespace LoyaltyNew\Core\Tests\Gacha;

use LoyaltyNew\Core\Gacha\DrawEngine;
use LoyaltyNew\Core\Prizes\TierConfig;
use PHPUnit\Framework\TestCase;

final class DrawEngineTest extends TestCase
{
    public function test_hasil_selalu_tier_valid(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        foreach (range(1, 100) as $seed) {
            $this->assertContains($engine->pick($seed), ['poin', 'voucher', 'merch']);
        }
    }

    public function test_distribusi_mendekati_bobot(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        $count = ['poin' => 0, 'voucher' => 0, 'merch' => 0];
        foreach (range(1, 10000) as $seed) {
            $count[$engine->pick($seed)]++;
        }
        $this->assertGreaterThan($count['voucher'], $count['poin']);
        $this->assertGreaterThan($count['merch'], $count['voucher']);
    }

    public function test_seed_sama_hasil_sama(): void
    {
        $engine = new DrawEngine(['poin' => 65, 'voucher' => 32, 'merch' => 3]);
        $this->assertSame($engine->pick(42), $engine->pick(42));
    }

    public function test_bobot_default_tepat_65_32_3(): void
    {
        $this->assertSame(['poin' => 65, 'voucher' => 32, 'merch' => 3], TierConfig::default());
    }

    public function test_distribusi_tepat_sesuai_bobot(): void
    {
        $engine = new DrawEngine(TierConfig::default());
        $count = ['poin' => 0, 'voucher' => 0, 'merch' => 0];
        foreach (range(1, 10000) as $seed) {
            $count[$engine->pick($seed)]++;
        }
        $this->assertEquals(6500, $count['poin']);
        $this->assertEquals(3200, $count['voucher']);
        $this->assertEquals(300, $count['merch']);
    }

    public function test_bobot_total_nol_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DrawEngine(['poin' => 0, 'voucher' => 0, 'merch' => 0]);
    }

    public function test_bobot_kosong_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DrawEngine([]);
    }
}
