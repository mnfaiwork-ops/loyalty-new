<?php

namespace LoyaltyNew\Core\Customers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\User;

/**
 * PIN 4 digit pelanggan (spec 2026-10-07). PIN hanya disimpan sebagai hash.
 * Lupa PIN diselesaikan CS: CS membuat PIN sementara, pelanggan wajib
 * menggantinya begitu masuk.
 */
final class CustomerPin
{
    public const LENGTH = 4;

    /** Pesan galat untuk PIN baru, atau null kalau PIN boleh dipakai. */
    public static function problem(string $pin): ?string
    {
        if (! preg_match('/^\d{'.self::LENGTH.'}$/', $pin)) {
            return 'PIN harus '.self::LENGTH.' angka.';
        }

        // Dengan 4 angka, pola ini yang pertama dicoba orang lain.
        if (self::isGuessable($pin)) {
            return 'PIN ini terlalu mudah ditebak. Hindari angka yang sama semua atau berurutan.';
        }

        return null;
    }

    public static function set(Customer $customer, string $pin): void
    {
        $customer->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_version' => $customer->pin_version + 1,
            'pin_must_change' => false,
            'pin_set_at' => now(),
        ])->save();
    }

    public static function matches(Customer $customer, string $pin): bool
    {
        return $customer->pin_hash !== null && Hash::check($pin, $customer->pin_hash);
    }

    /** Buat PIN sementara untuk dikirim CS. Perangkat yang diingat dengan PIN lama ikut keluar. */
    public static function issueTemporary(Customer $customer, User $by): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), self::LENGTH, '0', STR_PAD_LEFT);
        } while (self::isGuessable($pin));

        DB::transaction(function () use ($customer, $by, $pin) {
            $customer->forceFill([
                'pin_hash' => Hash::make($pin),
                'pin_version' => $customer->pin_version + 1,
                'pin_must_change' => true,
                'pin_set_at' => now(),
                'pin_reset_by' => $by->id,
                'pin_reset_at' => now(),
            ])->save();

            // Setiap reset disimpan, bukan hanya yang terakhir.
            DB::table('customer_pin_resets')->insert([
                'brand' => $customer->brand,
                'customer_id' => $customer->id,
                'reset_by' => $by->id,
                'created_at' => now(),
            ]);
        });

        return $pin;
    }

    private static function isGuessable(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
    }
}
