<?php

namespace LoyaltyNew\Core\Gacha;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gate\AwbMatcher;

/**
 * Pembatas percobaan masuk: resi + HP di /play/start dan PIN (spec §7, spec PIN 2026-10-07).
 *
 * Resi + HP dikunci pada tiga sumbu supaya tidak bisa dilewati dengan mengganti input:
 *  - per (awb, phone)      : brute-force HP untuk AWB tertentu.
 *  - per (phone, IP)       : uji satu HP ke banyak AWB dari satu perangkat/jaringan.
 *  - per phone, harian     : batas atas lintas IP untuk satu HP.
 *
 * PIN dikunci per (phone, IP) dan per phone harian.
 *
 * Kunci pendek per IP: orang lain yang hanya tahu nomor HP tidak bisa mengunci
 * pemiliknya dari jaringan lain. Batas harian per HP menahan tebakan yang
 * berpindah-pindah IP; kalau tercapai, CS membukanya lewat reset PIN.
 *
 * - HP dan IP tidak disimpan mentah (hash).
 * - Brand-scoped: satu brand tidak memengaruhi brand lain.
 * - Counter di-reset saat berhasil masuk, setelah kunci habis, dan kalau
 *   kegagalan terakhir sudah lebih lama dari jendela kunci. Tanpa reset ini,
 *   satu salah ketik sesudah kunci habis langsung mengunci lagi.
 *
 * Default: 5 gagal → kunci 15 menit; 20 gagal sehari per HP → kunci 24 jam.
 * Bisa diubah via config `loyalty.lockout.*` / env.
 */
final class AttemptLimiter
{
    private const DAY_MINUTES = 1440;

    public function __construct(
        private string $brand,
        private int $maxFails = 5,
        private int $lockMinutes = 15,
        private string $ip = '',
        private int $dailyMaxFails = 20,
    ) {
    }

    public static function fromConfig(string $brand, ?string $ip = null): self
    {
        return new self(
            $brand,
            (int) config('loyalty.lockout.max_fails', 5),
            (int) config('loyalty.lockout.minutes', 15),
            (string) $ip,
            (int) config('loyalty.lockout.daily_max_fails', 20),
        );
    }

    /** Sisa detik kunci resi + HP (maks dari semua sumbu), atau null kalau tidak terkunci. */
    public function lockedFor(string $awb, string $phone): ?int
    {
        return $this->maxRemaining([$this->pairKey($awb, $phone), ...$this->scoped($this->phoneKey($phone))]);
    }

    /** Catat satu kegagalan resi + HP di semua sumbu; true kalau sekarang terkunci. */
    public function recordFailure(string $awb, string $phone): bool
    {
        $pairLocked = $this->bump($this->pairKey($awb, $phone), $this->maxFails, $this->lockMinutes);

        return $this->bumpScoped($this->phoneKey($phone)) || $pairLocked;
    }

    /** Reset sumbu resi + HP saat berhasil masuk. */
    public function clear(string $awb, string $phone): void
    {
        $this->delete([$this->pairKey($awb, $phone), ...$this->scoped($this->phoneKey($phone))]);
    }

    /** Sisa detik kunci PIN nomor ini dari perangkat/jaringan ini, atau null. */
    public function pinLockedFor(string $phone): ?int
    {
        return $this->maxRemaining($this->scoped($this->pinKey($phone)));
    }

    public function recordPinFailure(string $phone): bool
    {
        return $this->bumpScoped($this->pinKey($phone));
    }

    /** Sisa PIN salah yang masih boleh sebelum nomor ini dikunci. */
    public function pinFailsLeft(string $phone): int
    {
        [$short, $day] = $this->scoped($this->pinKey($phone));

        return max(0, min(
            $this->maxFails - (int) ($this->row($short)->fails ?? 0),
            $this->dailyMaxFails - (int) ($this->row($day)->fails ?? 0),
        ));
    }

    public function lockMinutes(): int
    {
        return $this->lockMinutes;
    }

    /** PIN benar: hapus hitungan PIN nomor ini di semua perangkat. */
    public function clearPin(string $phone): void
    {
        $this->deletePrefix($this->pinKey($phone));
    }

    /** Reset PIN oleh CS: buka semua kunci nomor ini, PIN maupun resi, di semua perangkat. */
    public function clearPhone(string $phone): void
    {
        $this->deletePrefix($this->pinKey($phone));
        $this->deletePrefix($this->phoneKey($phone));
    }

    /**
     * Jalankan cek PIN satu per satu per nomor HP. Tanpa ini, banyak request
     * bersamaan sama-sama lolos cek kunci sebelum ada yang mencatat gagal.
     *
     * @template T
     * @param  callable(): T  $check
     * @return T
     */
    public function serialize(string $phone, callable $check): mixed
    {
        return Cache::lock('attempt:'.$this->pinKey($phone), 15)->block(10, $check);
    }

    public function pinKey(string $phone): string
    {
        return hash('sha256', $this->brand.'|pin|'.AwbMatcher::normalizePhone($phone));
    }

    public function pairKey(string $awb, string $phone): string
    {
        return hash('sha256', $this->brand.'|pair|'.AwbMatcher::normalizeAwb($awb).'|'.AwbMatcher::normalizePhone($phone));
    }

    public function phoneKey(string $phone): string
    {
        return hash('sha256', $this->brand.'|phone|'.AwbMatcher::normalizePhone($phone));
    }

    /** @return array{0:string,1:string} key pendek per IP dan key harian untuk satu prefix nomor */
    private function scoped(string $prefix): array
    {
        return [$prefix.':ip:'.substr(hash('sha256', $this->ip), 0, 32), $prefix.':day'];
    }

    private function bumpScoped(string $prefix): bool
    {
        [$short, $day] = $this->scoped($prefix);
        $shortLocked = $this->bump($short, $this->maxFails, $this->lockMinutes);
        $dayLocked = $this->bump($day, $this->dailyMaxFails, self::DAY_MINUTES);

        return $shortLocked || $dayLocked;
    }

    /** @param  array<int,string>  $keys */
    private function maxRemaining(array $keys): ?int
    {
        $max = max(array_map(fn (string $key) => $this->remaining($key), $keys));

        return $max > 0 ? $max : null;
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
    private function bump(string $key, int $maxFails, int $lockMinutes): bool
    {
        $this->resetIfStale($key, $lockMinutes);

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

        if ($fails >= $maxFails) {
            DB::table('play_attempts')
                ->where('brand', $this->brand)->where('key', $key)
                ->update(['locked_until' => now()->addMinutes($lockMinutes)]);

            return true;
        }

        return false;
    }

    /** Mulai hitungan dari nol kalau kunci sudah habis atau kegagalan terakhir sudah basi. */
    private function resetIfStale(string $key, int $lockMinutes): void
    {
        $row = $this->row($key);
        if (! $row) {
            return;
        }

        $lockExpired = $row->locked_until && Carbon::parse($row->locked_until)->isPast();
        $staleFail = $row->last_fail_at
            && Carbon::parse($row->last_fail_at)->lt(now()->subMinutes($lockMinutes));

        if ($lockExpired || $staleFail) {
            DB::table('play_attempts')
                ->where('brand', $this->brand)->where('key', $key)
                ->update(['fails' => 0, 'locked_until' => null]);
        }
    }

    /** @param  array<int,string>  $keys */
    private function delete(array $keys): void
    {
        DB::table('play_attempts')->where('brand', $this->brand)->whereIn('key', $keys)->delete();
    }

    private function deletePrefix(string $prefix): void
    {
        DB::table('play_attempts')
            ->where('brand', $this->brand)
            ->where(fn ($q) => $q->where('key', $prefix)->orWhere('key', 'like', $prefix.':%'))
            ->delete();
    }

    private function row(string $key): ?object
    {
        return DB::table('play_attempts')
            ->where('brand', $this->brand)
            ->where('key', $key)
            ->first();
    }
}
