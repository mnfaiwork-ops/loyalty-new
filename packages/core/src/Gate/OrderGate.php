<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber kebenaran "paket sudah diterima".
 * Fase 1: StubOrderGate. Nanti: MarkomApiOrderGate (tanpa ubah pemanggil).
 */
interface OrderGate
{
    public function isDelivered(string $awb, string $phone): GateResult;
}
