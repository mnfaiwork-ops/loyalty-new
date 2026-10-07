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

    /** Gagal antar, retur, atau batal (FairTrack + WMS Soscom). Selalu ditolak. */
    private const NOT_RECEIVED = ['request_return', 'returned', 'request_cancel', 'cancelled'];

    /**
     * Aturan kelulusan gate (spec §3): penentu UTAMA status fulfillment selesai
     * (`complete` di FairTrack, `completed` di `orders.status` WMS).
     * Padanan aman: `status_order='completed'` (konsisten, mis. order lama).
     * Sengaja TIDAK menerima `status_order='sent'` saja — data live membuktikan
     * `sent` bisa berarti belum sampai (491 order `sent`+`problem`).
     */
    public function isDelivered(): bool
    {
        if (in_array($this->statusFulfillment, self::NOT_RECEIVED, true)
            || in_array($this->statusOrder, self::NOT_RECEIVED, true)) {
            return false;
        }

        if (in_array($this->statusFulfillment, ['complete', 'completed'], true)) {
            return true;
        }

        return $this->statusOrder === 'completed';
    }
}
