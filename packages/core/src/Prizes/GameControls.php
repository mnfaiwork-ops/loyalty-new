<?php

namespace LoyaltyNew\Core\Prizes;

use Illuminate\Support\Facades\DB;

/**
 * Kendali Gatcha per brand dari Game Admin (feedback testing 2026-10-15):
 * - paused: semua kado jadi poin, misalnya saat pengiriman hadiah belum siap.
 * - big_prize_chance: peluang (%) hadiah utama ditaruh di satu papan, selama kuota ada.
 */
final class GameControls
{
    public const DEFAULTS = ['paused' => false, 'big_prize_chance' => 20];

    private static function key(?string $brand): string
    {
        return 'game_controls:'.($brand ?? (string) config('app.brand'));
    }

    /** @return array{paused:bool,big_prize_chance:int} */
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
