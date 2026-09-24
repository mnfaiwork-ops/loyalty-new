<?php

namespace LoyaltyNew\Core\Tests\Gacha;

use LoyaltyNew\Core\Gacha\Grid;
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    public function test_grid_24_slot(): void
    {
        $this->assertSame(24, Grid::SLOTS);
        $this->assertSame(3, Grid::PICKS);
        $this->assertCount(24, Grid::slots());
        $this->assertSame(1, Grid::slots()[0]);
        $this->assertSame(24, Grid::slots()[23]);
    }
}
