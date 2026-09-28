<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;

/**
 * Pembatas percobaan masuk game (AWB+HP) — kunci akses 15 menit setelah 5 kali
 * salah (spec §7).
 *
 * Dikunci pada DUA sumbu supaya tidak bisa dilewati dengan mengganti input:
 *  - per (awb, phone): brute-force HP untuk AWB tertentu.
 *  - per phone       : uji satu HP ke banyak AWB sekaligus.
 *
 * - HP tidak disimpan mentah (hash).
 * - Brand-scoped: satu brand tidak memengaruhi brand lain.
 * - Counter di-reset saat berhasil masuk, setelah kunci habis, dan kalau
 *   kegagalan terakhir sudah lebih lama dari jendela kunci. Tanpa reset ini,
 *   satu salah ketik sesudah kunci habis langsung mengunci lagi.
 *
 * Default: 5 percobaan gagal → kunci 15 menit. Bisa diubah via config
 * `loyalty.lockout.*` / env.
 */
final class AttemptLimiter
{
    public function __construct(
        private string $brand,
        private int $maxFails = 5,
        private int $lockMinutes = 15,
    ) {
    }

    public static function fromConfig(string $brand): self
    {
        return new self(
            $brand,
            (int) config('loyalty.lockout.max_fails', 5),
            (int) config('loyalty.lockout.minutes', 15),
        );
    }

    /** Sisa detik kunci (maks dari kedua sumbu), atau null kalau tidak terkunci. */
    public function lockedFor(string $awb, string $phone): ?int
    {
        $pair = $this->remaining($this->pairKey($awb, $phone));
        $phoneOnly = $this->remaining($this->phoneKey($phone));

        $max = max((int) $pair, (int) $phoneOnly);

        return $max > 0 ? $max : null;
    }

    /** Catat satu kegagalan pada kedua sumbu; true kalau sekarang terkunci. */
    public function recordFailure(string $awb, string $phone): bool
    {
        $pairLocked = $this->bump($this->pairKey($awb, $phone));
        $phoneLocked = $this->bump($this->phoneKey($phone));

        return $pairLocked || $phoneLocked;
    }

    /** Reset kedua sumbu saat berhasil masuk. */
    public function clear(string $awb, string $phone): void
    {
        DB::table('play_attempts')
            ->where('brand', $this->brand)
            ->whereIn('key', [$this->pairKey($awb, $phone), $this->phoneKey($phone)])
            ->delete();
    }

    public function pairKey(string $awb, string $phone): string
    {
        return hash('sha256', $this->brand.'|pair|'.AwbMatcher::normalizeAwb($awb).'|'.AwbMatcher::normalizePhone($phone));
    }

    public function phoneKey(string $phone): string
    {
        return hash('sha256', $this->brand.'|phone|'.AwbMatcher::normalizePhone($phone));
    }

    /** Sisa detik kunci untuk satu key, atau 0. */
    private function remaining(string $key): int
    {
        $row = $this->row($key);
        if (! $row || ! $row->locked_until) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInSeconds(Carbon::parse($row->locked_until), false)));
    }

    /** Tambah hitungan gagal satu key; kunci kalau sudah mencapai batas. */
    private function bump(string $key): bool
    {
        $this->resetIfStale($key);

        DB::table('play_attempts')->upsert(
            [[
                'brand' => $this->brand,
                'key' => $key,
                'fails' => 1,
                'locked_until' => null,
                'last_fail_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['brand', 'key'],
            ['fails' => DB::raw('play_attempts.fails + 1'), 'last_fail_at' => now(), 'updated_at' => now()],
        );

        $fails = (int) (DB::table('play_attempts')
            ->where('brand', $this->brand)->where('key', $key)->value('fails') ?? 0);

        if ($fails >= $this->maxFails) {
            DB::table('play_attempts')
                ->where('brand', $this->brand)->where('key', $key)
                ->update(['locked_until' => now()->addMinutes($this->lockMinutes)]);

            return true;
        }

        return false;
    }

    /** Mulai hitungan dari nol kalau kunci sudah habis atau kegagalan terakhir sudah basi. */
    private function resetIfStale(string $key): void
    {
        $row = $this->row($key);
        if (! $row) {
            return;
        }

        $lockExpired = $row->locked_until && Carbon::parse($row->locked_until)->isPast();
        $staleFail = $row->last_fail_at
            && Carbon::parse($row->last_fail_at)->lt(now()->subMinutes($this->lockMinutes));

        if ($lockExpired || $staleFail) {
            DB::table('play_attempts')
                ->where('brand', $this->brand)->where('key', $key)
                ->update(['fails' => 0, 'locked_until' => null]);
        }
    }

    private function row(string $key): ?object
    {
        return DB::table('play_attempts')
            ->where('brand', $this->brand)
            ->where('key', $key)
            ->first();
    }
}
