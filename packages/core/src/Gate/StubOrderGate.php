<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Implementasi sementara fase 1.
 * Selalu lolos SAAT AWB dan HP sama-sama tidak kosong, supaya alur bisa dites end-to-end.
 * Diganti MarkomApiOrderGate saat API Markom siap.
 */
final class StubOrderGate implements OrderGate
{
    public function isDelivered(string $awb, string $phone): GateResult
    {
        if (trim($awb) === '') {
            return GateResult::rejected('AWB kosong');
        }

        if (trim($phone) === '') {
            return GateResult::rejected('Nomor HP kosong');
        }

        // Stub tidak tahu tanggal order: anggap hari ini, supaya periode program tetap bisa dites di dev.
        return GateResult::delivered(orderedAt: (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d'));
    }
}
