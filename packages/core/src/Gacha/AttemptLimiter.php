<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Facades\DB;

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
 * - Counter di-reset saat berhasil masuk.
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
        return hash('sha256', $this->brand.'|pair|'.trim($awb).'|'.$this->normalizePhone($phone));
    }

    public function phoneKey(string $phone): string
    {
        return hash('sha256', $this->brand.'|phone|'.$this->normalizePhone($phone));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    /** Sisa detik kunci untuk satu key, atau 0. */
    private function remaining(string $key): int
    {
        $row = $this->row($key);
        if (! $row || ! $row->locked_until) {
            return 0;
        }

        return max(0, strtotime((string) $row->locked_until) - time());
    }

    /** Tambah hitungan gagal satu key; kunci kalau sudah mencapai batas. */
    private function bump(string $key): bool
    {
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

    private function row(string $key): ?object
    {
        return DB::table('play_attempts')
            ->where('brand', $this->brand)
            ->where('key', $key)
            ->first();
    }
}
