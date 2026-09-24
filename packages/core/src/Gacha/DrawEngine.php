<?php

namespace LoyaltyNew\Core\Gacha;

/** Undian berbobot deterministik terhadap seed. Murni: tanpa DB, tanpa waktu. */
final class DrawEngine
{
    private int $total;

    public function __construct(private array $weights)
    {
        $this->total = array_sum($weights);
        if ($this->total <= 0) {
            throw new \InvalidArgumentException('Bobot total harus > 0');
        }
    }

    public function pick(int $seed): string
    {
        $target = (($seed - 1) % $this->total) + 1;
        $cursor = 0;
        foreach ($this->weights as $tier => $weight) {
            $cursor += $weight;
            if ($target <= $cursor) {
                return $tier;
            }
        }
        return array_key_last($this->weights);
    }
}
