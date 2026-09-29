<?php

namespace LoyaltyNew\Core\Points;

final class InsufficientPointsException extends \RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $required)
    {
        parent::__construct('Poinmu kurang '.number_format($required - $balance, 0, ',', '.').' lagi.');
    }
}
