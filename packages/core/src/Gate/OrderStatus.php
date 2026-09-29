<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Satu baris status order yang dibutuhkan gate. Netral dari sumber
 * (HTTP Markom atau SQLite FairTrack).
 */
final class OrderStatus
{
    public function __construct(
        public readonly string $awb,
        public readonly ?string $phone,
        public readonly ?string $statusOrder,
        public readonly ?string $statusFulfillment,
        public readonly ?string $customerName = null,
    ) {
    }

    /**
     * Aturan kelulusan gate (spec §3): penentu UTAMA `status_fulfillment='complete'`.
     * Padanan aman: `status_order='completed'` (konsisten, mis. order lama).
     * Sengaja TIDAK menerima `status_order='sent'` saja — data live membuktikan
     * `sent` bisa berarti belum sampai (491 order `sent`+`problem`).
     */
    public function isDelivered(): bool
    {
        if ($this->statusFulfillment !== null && $this->statusFulfillment === 'complete') {
            return true;
        }

        return $this->statusOrder === 'completed';
    }
}
