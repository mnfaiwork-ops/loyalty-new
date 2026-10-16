<?php

namespace LoyaltyNew\Core\Points;

use Illuminate\Support\Facades\DB;

/**
 * Aturan poin per brand (spec §2 #2), bisa diubah Game Admin lewat tabel settings.
 * per_thousand + min_percent: nilai kado poin ikut nominal order (feedback 2026-10-15).
 * per_gift dipakai kalau nominal order tidak diketahui (gate stub, sesi lama).
 */
final class LoyaltySettings
{
    public const DEFAULTS = [
        'per_gift' => 200, 'min_redeem' => 500, 'validity_months' => 12,
        'per_thousand' => 1, 'min_percent' => 95,
    ];

    private static function key(?string $brand): string
    {
        return 'points:'.($brand ?? (string) config('app.brand'));
    }

    /** @return array{per_gift:int,min_redeem:int,validity_months:int,per_thousand:int,min_percent:int} */
    public static function current(?string $brand = null): array
    {
        $raw = DB::table('settings')->where('key', self::key($brand))->value('value');
        $stored = $raw ? json_decode($raw, true) : [];
        $values = self::DEFAULTS;

        foreach (array_keys(self::DEFAULTS) as $key) {
            // Pengaturan lama belum punya per_thousand/min_percent: kunci itu tetap default.
            if (is_array($stored) && is_int($stored[$key] ?? null) && $stored[$key] > 0) {
                $values[$key] = $stored[$key];
            }
        }

        return $values;
    }

    public static function store(array $values, ?string $brand = null): void
    {
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            $clean[$key] = (int) ($values[$key] ?? $default);
        }
        $clean['min_percent'] = max(1, min(100, $clean['min_percent']));

        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand)],
            ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
