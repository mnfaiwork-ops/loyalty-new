<?php

namespace LoyaltyNew\Core\Gate;

use Illuminate\Support\Facades\Cache;

/**
 * Membungkus sumber status dengan cache berbatas waktu, supaya gate saat main
 * tidak memanggil Markom berulang untuk AWB yang sama.
 *
 * - TTL pendek supaya status tidak basi (default 60 dtk).
 * - Webhook Markom (lihat MarkomWebhookController) mem-bust cache AWB terkait.
 * - Fail-closed tetap: kalau sumber null, TIDAK dicache sebagai "ada".
 *
 * CATATAN: hanya hasil yang ditemukan (non-null) yang dicache. Order yang belum
 * ada sengaja di-query ulang tiap kali agar paket yang baru berubah status
 * cepat terdeteksi tanpa webhook.
 */
final class CachedOrderStatusSource implements OrderStatusSource
{
    public function __construct(
        private OrderStatusSource $inner,
        private int $ttlSeconds = 60,
        private string $brand = 'default',
    ) {
    }

    public function find(string $awb, string $phone = ''): ?OrderStatus
    {
        $key = self::cacheKey($this->brand, $awb);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return new OrderStatus(
                awb: (string) $cached['awb'],
                phone: $cached['phone'] ?? null,
                statusOrder: $cached['status_order'] ?? null,
                statusFulfillment: $cached['status_fulfillment'] ?? null,
                customerName: $cached['customer_name'] ?? null,
                orderAmount: $cached['order_amount'] ?? null,
                orderedAt: $cached['ordered_at'] ?? null,
            );
        }

        $status = $this->inner->find($awb, $phone);

        if ($status !== null) {
            Cache::put($key, [
                'awb' => $status->awb,
                'phone' => $status->phone,
                'status_order' => $status->statusOrder,
                'status_fulfillment' => $status->statusFulfillment,
                'customer_name' => $status->customerName,
                'order_amount' => $status->orderAmount,
                'ordered_at' => $status->orderedAt,
            ], $this->ttlSeconds);
        }

        return $status;
    }

    /** Buang cache satu AWB (dipakai webhook). */
    public static function forget(string $brand, string $awb): void
    {
        Cache::forget(self::cacheKey($brand, $awb));
    }

    private static function cacheKey(string $brand, string $awb): string
    {
        return 'markom:order:'.$brand.':'.strtoupper(trim($awb));
    }
}
