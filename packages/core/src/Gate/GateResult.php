<?php

namespace LoyaltyNew\Core\Gate;

final class GateResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $reason = null,
    ) {
    }

    public static function delivered(): self
    {
        return new self(true, null);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
