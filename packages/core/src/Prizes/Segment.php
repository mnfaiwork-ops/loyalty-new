<?php

namespace LoyaltyNew\Core\Prizes;

use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PrizeCatalog;

/**
 * Jenis pelanggan untuk mengatur isi kado (permintaan Fai 2026-10-21). Aturannya hanya
 * terlihat di panel admin, tidak pernah ditampilkan ke pelanggan.
 *
 * Pelanggan baru = nomor HP yang belum punya sesi Gatcha di brand ini sebelum paket ini.
 * Pelanggan lama = sudah pernah main dari paket sebelumnya. Pembeli lama yang baru pertama
 * kali main di program dihitung pelanggan baru.
 */
final class Segment
{
    public const BARU = 'baru';

    public const LAMA = 'lama';

    public const SEMUA = 'semua';

    /** Jenis pelanggan sebuah sesi. */
    public const SESSION = [self::BARU, self::LAMA];

    /** Pilihan "untuk siapa" di hadiah Gatcha. */
    public const PRIZE = [self::SEMUA, self::BARU, self::LAMA];

    public static function label(string $segment): string
    {
        return match ($segment) {
            self::BARU => 'Pelanggan baru',
            self::LAMA => 'Pelanggan lama',
            default => 'Semua pelanggan',
        };
    }

    /** Bawaan kalau hadiah belum diatur: hadiah utama mulai pesanan kedua, lainnya untuk semua. */
    public static function defaultFor(?string $tier): string
    {
        return $tier === 'hadiah_besar' ? self::LAMA : self::SEMUA;
    }

    /** Segment efektif sebuah hadiah (kolom kosong = ikut bawaan tier). */
    public static function ofPrize(PrizeCatalog $prize): string
    {
        return in_array($prize->segment, self::PRIZE, true) ? $prize->segment : self::defaultFor($prize->tier);
    }

    public static function allows(PrizeCatalog $prize, ?string $sessionSegment): bool
    {
        $segment = self::ofPrize($prize);

        return $segment === self::SEMUA || $sessionSegment === null || $segment === $sessionSegment;
    }

    /** Jenis pelanggan untuk paket berikutnya dari customer ini. */
    public static function forNewSession(int $customerId, string $brand): string
    {
        return GameSession::withoutGlobalScopes()->where('customer_id', $customerId)->where('brand', $brand)->exists()
            ? self::LAMA
            : self::BARU;
    }

    /** Jenis pelanggan sesi yang sudah ada. Sesi sebelum fitur ini dihitung dari riwayatnya. */
    public static function ofSession(GameSession $session): string
    {
        if (in_array($session->segment, self::SESSION, true)) {
            return $session->segment;
        }

        return GameSession::withoutGlobalScopes()->where('customer_id', $session->customer_id)
            ->where('brand', $session->brand)->where('id', '<', $session->id)->exists()
            ? self::LAMA
            : self::BARU;
    }
}
