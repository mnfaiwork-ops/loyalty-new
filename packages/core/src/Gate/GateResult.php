<?php

namespace LoyaltyNew\Core\Gate;

final class GateResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly ?string $reason = null,
        public readonly ?string $customerName = null,
    ) {
    }

    public static function delivered(?string $customerName = null): self
    {
        return new self(true, null, $customerName);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason);
    }
}
