<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;

/**
 * Kendali Gatcha per brand dari Game Admin (feedback testing 2026-10-15):
 * - paused: semua kado jadi poin, misalnya saat pengiriman hadiah belum siap.
 * - big_prize_chance: peluang (%) hadiah utama ditaruh di satu papan, selama kuota ada.
 * - order_from / order_until: periode program (Y-m-d, WIB). Hanya order yang dibuat
 *   di dalam periode ini yang bisa ikut Gatcha. Kosong = tanpa batas di sisi itu.
 */
final class GameControls
{
    public const DEFAULTS = ['paused' => false, 'big_prize_chance' => 20, 'order_from' => null, 'order_until' => null];

    private static function key(?string $brand): string
    {
        return 'game_controls:'.($brand ?? (string) config('app.brand'));
    }

    /** @return array{paused:bool,big_prize_chance:int,order_from:?string,order_until:?string} */
    public static function current(?string $brand = null): array
    {
        $raw = DB::table('settings')->where('key', self::key($brand))->value('value');
        $stored = $raw ? json_decode($raw, true) : [];
        $values = self::DEFAULTS;

        if (is_array($stored)) {
            if (is_bool($stored['paused'] ?? null)) {
                $values['paused'] = $stored['paused'];
            }
            $chance = $stored['big_prize_chance'] ?? null;
            if (is_int($chance) && $chance >= 0 && $chance <= 100) {
                $values['big_prize_chance'] = $chance;
            }
            foreach (['order_from', 'order_until'] as $key) {
                if (is_string($stored[$key] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored[$key])) {
                    $values[$key] = $stored[$key];
                }
            }
        }

        return $values;
    }

    public static function store(array $values, ?string $brand = null): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand)],
            ['value' => json_encode([
                'paused' => (bool) $values['paused'],
                'big_prize_chance' => max(0, min(100, (int) $values['big_prize_chance'])),
                'order_from' => ($values['order_from'] ?? null) ?: null,
                'order_until' => ($values['order_until'] ?? null) ?: null,
            ]), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public static function paused(?string $brand = null): bool
    {
        return self::current($brand)['paused'];
    }

    /** Lempar dadu untuk satu papan: hadiah utama boleh ditaruh di papan ini? */
    public static function rollBigPrize(?string $brand = null): bool
    {
        $chance = self::current($brand)['big_prize_chance'];

        return $chance > 0 && random_int(1, 100) <= $chance;
    }
}
