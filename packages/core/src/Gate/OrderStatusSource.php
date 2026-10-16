<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber data status order. Implementasi: API Soscom, HTTP Markom, SQLite FairTrack.
 * Memisahkan "dari mana data" dari "aturan kelulusan" (OrderStatus::isDelivered).
 */
interface OrderStatusSource
{
    /**
     * Ambil status satu AWB. Return null kalau tidak ditemukan.
     * $phone wajib untuk sumber yang mencocokkan HP di server (Soscom); sumber lain mengabaikannya.
     */
    public function find(string $awb, string $phone = ''): ?OrderStatus;
}
