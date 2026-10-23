<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Sumber status order untuk versi demo (permintaan Fai 2026-10-23: tim Markom
 * merekam alur pelanggan tanpa menyentuh produksi). Membaca daftar resi dummy
 * dari CSV, jadi aturan gate asli (cocok resi + HP, paket diterima, periode
 * program) tetap berlaku dan pesannya sama dengan produksi.
 *
 * Kolom CSV: awb, phone, customer_name, order_amount, ordered_at,
 * status_order, status_fulfillment. Fail-closed: file tidak terbaca = null.
 */
final class DemoOrderStatusSource implements OrderStatusSource
{
    public function __construct(private string $file)
    {
    }

    public function find(string $awb, string $phone = ''): ?OrderStatus
    {
        $awb = AwbMatcher::normalizeAwb($awb);
        if ($awb === '' || trim($this->file) === '' || ! is_readable($this->file)) {
            return null;
        }

        $handle = fopen($this->file, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if (! is_array($header)) {
                return null;
            }

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                if (count($values) !== count($header)) {
                    continue;
                }

                $row = array_combine($header, $values);
                if (AwbMatcher::normalizeAwb($row['awb'] ?? '') !== $awb) {
                    continue;
                }

                return new OrderStatus(
                    awb: $row['awb'],
                    phone: $row['phone'] ?? null,
                    statusOrder: ($row['status_order'] ?? '') ?: null,
                    statusFulfillment: ($row['status_fulfillment'] ?? '') ?: null,
                    customerName: ($row['customer_name'] ?? '') ?: null,
                    orderAmount: is_numeric($row['order_amount'] ?? null) ? (int) $row['order_amount'] : null,
                    orderedAt: ($row['ordered_at'] ?? '') ?: null,
                );
            }
        } finally {
            fclose($handle);
        }

        return null;
    }
}
