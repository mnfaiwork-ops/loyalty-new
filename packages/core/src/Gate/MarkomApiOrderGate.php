<?php

namespace LoyaltyNew\Core\Gate;

/**
 * Gate produksi: cek "paket sudah diterima" via sumber status nyata
 * (API Markom / FairTrack SQLite), lalu pastikan AWB cocok dengan nomor HP.
 *
 * Aturan (spec §3, §9 cek #1 & #5):
 *  1. Order harus ditemukan.
 *  2. status_fulfillment harus 'complete' (penentu utama).
 *  3. AWB + HP harus COCOK (anti-fraud, AwbMatcher fail-closed).
 *
 * Fail-closed: sumber tidak bisa dibaca / order tak ada → ditolak.
 *
 * Order tidak ada dan HP tidak cocok memberi pesan yang sama, supaya form
 * tidak bisa dipakai menebak resi mana yang ada.
 */
final class MarkomApiOrderGate implements OrderGate
{
    public const NOT_MATCHED = 'Resi atau nomor HP tidak cocok';

    public function __construct(private OrderStatusSource $source)
    {
    }

    public function isDelivered(string $awb, string $phone): GateResult
    {
        if (trim($awb) === '') {
            return GateResult::rejected('AWB kosong');
        }

        if (trim($phone) === '') {
            return GateResult::rejected('Nomor HP kosong');
        }

        $order = $this->source->find($awb, $phone);

        // Anti-fraud: AWB curian akan punya HP berbeda. Fail-closed. Dicek sebelum
        // status supaya "belum diterima" hanya terlihat oleh pemilik resi + HP.
        if ($order === null || ! AwbMatcher::matches($awb, $phone, $order->awb, (string) $order->phone)) {
            return GateResult::rejected(self::NOT_MATCHED);
        }

        if (! $order->isDelivered()) {
            return GateResult::rejected('Paket belum diterima');
        }

        return GateResult::delivered($order->customerName, $order->orderAmount, $order->orderedAt);
    }
}
