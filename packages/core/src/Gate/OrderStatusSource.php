<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber data status order. Implementasi: HTTP Markom, SQLite FairTrack.
 * Memisahkan "dari mana data" dari "aturan kelulusan" (OrderStatus::isDelivered).
 */
interface OrderStatusSource
{
    /** Ambil status satu AWB. Return null kalau tidak ditemukan. */
    public function find(string $awb): ?OrderStatus;
}
