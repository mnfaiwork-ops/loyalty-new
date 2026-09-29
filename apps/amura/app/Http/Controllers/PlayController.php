<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RememberedCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Gacha\Grid;
use LoyaltyNew\Core\Gacha\SessionExpiredException;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\Customer;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Models\PointLot;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\PrizeAwarder;

final class PlayController extends Controller
{
    public function __construct(private SessionService $sessions)
    {
    }

    public function form()
    {
        return view('play.form');
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'awb' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:32'],
        ], [
            'awb.required' => 'Nomor resi wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'max' => 'Isian terlalu panjang. Cek lagi angkanya.',
        ]);

        // Form menampilkan "+62" di depan kolom HP, jadi "812..." berarti "0812...".
        $digits = preg_replace('/\D+/', '', $data['phone']);
        if ($digits !== '' && ! str_starts_with($digits, '0') && ! str_starts_with($digits, '62')) {
            $data['phone'] = '0'.$digits;
        }

        $limiter = AttemptLimiter::fromConfig((string) config('app.brand'));

        // Kunci akses: tolak lebih awal kalau sudah 5x salah (spec §7).
        if (($remaining = $limiter->lockedFor($data['awb'], $data['phone'])) !== null) {
            $minutes = (int) ceil($remaining / 60);

            return back()->withErrors([
                'awb' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit.",
            ])->withInput();
        }

        try {
            $session = $this->sessions->open($data['awb'], $data['phone']);
        } catch (SessionExpiredException $e) {
            // Input benar, masa main saja yang habis: jangan dihitung percobaan gagal.
            // Resi + HP sudah cocok, jadi pelanggan tetap masuk ke Beranda untuk
            // melihat poin dan me-redeem hadiah yang batasnya belum lewat (spec §7).
            $customer = $e->customerId ? Customer::find($e->customerId) : null;
            if (! $customer) {
                return back()->withErrors(['awb' => $e->getMessage()])->withInput();
            }

            $limiter->clear($data['awb'], $data['phone']);

            return redirect()->route('home')
                ->with('ok', 'Masa main untuk paket ini sudah berakhir. Poin dan hadiahmu tetap bisa dilihat di sini.')
                ->withCookie(RememberedCustomer::cookieFor($customer));
        } catch (\RuntimeException $e) {
            $limiter->recordFailure($data['awb'], $data['phone']);

            return back()->withErrors(['awb' => $e->getMessage()])->withInput();
        }

        $limiter->clear($data['awb'], $data['phone']);

        $customer = Customer::findOrFail($session->customer_id);
        $target = $session->chance_used < $session->chance_total
            ? redirect()->route('play.board', $session->session_token)
            : redirect()->route('home');

        return $target->withCookie(RememberedCustomer::cookieFor($customer));
    }

    public function board(string $token)
    {
        $session = $this->session($token);

        if (SessionService::isExpired($session)) {
            return redirect()->route('play.form')
                ->withErrors(['awb' => (new SessionExpiredException)->getMessage()]);
        }

        // Ronde yang belum selesai (mis. halaman di-refresh) ditampilkan lagi,
        // supaya pelanggan bisa melanjutkan, bukan terjebak di kotak yang terkunci.
        $round = $this->round($session);
        $revealed = $round->count() === Grid::PICKS && $round->every(fn (GamePrize $p) => $p->opened)
            ? $this->revealed($round)
            : [];

        return view('play.board', [
            'session' => $session,
            'prizes' => $session->prizes()->orderBy('slot')->get(),
            'pendingSlots' => $round->pluck('slot')->values()->all(),
            'pendingRevealed' => $revealed,
            'taken' => $this->taken($session),
        ]);
    }

    public function pick(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'slots' => ['required', 'array', 'size:3'],
            'slots.*' => ['required', 'integer', 'distinct'],
        ]);

        // Dikunci per sesi supaya dua pick bersamaan tidak sama-sama lolos cek ronde.
        return DB::transaction(function () use ($token, $data) {
            $session = $this->lockedSession($token);

            if ($error = $this->playableError($session)) {
                return $error;
            }

            foreach ($data['slots'] as $slot) {
                $available = $slot >= 1 && $slot <= Grid::SLOTS
                    && $session->prizes()
                        ->where('slot', $slot)
                        ->where('opened', false)
                        ->where('taken', false)
                        ->exists();

                if (! $available) {
                    return response()->json(['error' => 'Slot tidak valid'], 422);
                }
            }

            if ($this->round($session)->isNotEmpty()) {
                return response()->json(['error' => 'Sudah memilih slot ronde ini'], 422);
            }

            $session->prizes()->whereIn('slot', $data['slots'])->update(['picked' => true]);

            return response()->json(['ok' => true]);
        });
    }

    public function reveal(string $token): JsonResponse
    {
        return DB::transaction(function () use ($token) {
            $session = $this->lockedSession($token);

            if (SessionService::isExpired($session)) {
                return $this->expiredResponse();
            }

            $round = $this->round($session);

            if ($round->count() !== Grid::PICKS) {
                return response()->json(['error' => 'Belum memilih 3 slot'], 422);
            }

            // Hadiah utama langsung diambil saat terbuka, jadi kesempatan harus
            // masih ada sebelum kado dibuka (sesi sudah dikunci, cek ini konsisten).
            if ($session->chance_used >= $session->chance_total) {
                return response()->json(['error' => 'Kesempatan habis'], 422);
            }

            $round = $this->sessions->openRound($session, $round);
            $bigPrize = $round->firstWhere('tier', 'hadiah_besar');

            if (! $bigPrize) {
                return response()->json(['ok' => true, 'revealed' => $this->revealed($round)]);
            }

            // Hadiah utama langsung masuk keranjang; dua kado lain otomatis tidak terpilih.
            $this->sessions->consumeChance($session);

            return response()->json($this->keep($session, $round, $bigPrize) + ['auto_taken' => true]);
        });
    }

    public function take(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($token, $data) {
            $session = $this->lockedSession($token);

            if (SessionService::isExpired($session)) {
                return $this->expiredResponse();
            }

            $round = $this->round($session);

            if (! $round->firstWhere('slot', $data['slot'])) {
                return response()->json(['error' => 'Slot belum dipilih'], 422);
            }

            // Semua cek dulu, baru tulis: response 422 di tengah transaksi tidak me-rollback.
            if (! $this->sessions->consumeChance($session)) {
                return response()->json(['error' => 'Kesempatan habis'], 422);
            }

            $round = $this->sessions->openRound($session, $round);

            // Take tanpa reveal: hadiah utama di ronde ini tetap yang masuk keranjang.
            $chosen = $round->firstWhere('tier', 'hadiah_besar')
                ?? $round->firstWhere('slot', $data['slot']);

            return response()->json($this->keep($session, $round, $chosen));
        });
    }

    /** Simpan satu hadiah dari ronde, lepas dua lainnya, lalu beri poin atau terbitkan klaim. */
    private function keep(GameSession $session, Collection $round, GamePrize $chosen): array
    {
        $chosen->update(['taken' => true, 'opened' => true]);

        $others = $round->reject(fn (GamePrize $prize) => $prize->id === $chosen->id);
        $session->prizes()->whereIn('id', $others->pluck('id'))->update(['picked' => false]);

        $award = PrizeAwarder::award($session, $chosen);
        $claim = $award['type'] === 'claim' ? $award['claim'] : null;

        return [
            'ok' => true,
            'revealed' => $this->revealed($round),
            'chosen' => $chosen->slot,
            'prize_name' => $claim?->prize_name,
            'points' => $award['type'] === 'points' ? $award['points'] : null,
            'balance' => $award['type'] === 'points' ? $award['balance'] : null,
            'claim_code' => $claim?->claim_code,
            'claim_expires_at' => $claim?->expires_at->toDateString(),
            'chance_left' => max(0, $session->chance_total - $session->fresh()->chance_used),
            'chance_total' => $session->chance_total,
        ];
    }

    /** Slot ronde aktif: sudah dipilih tapi belum disimpan. */
    private function round(GameSession $session): Collection
    {
        return $session->prizes()
            ->where('picked', true)
            ->where('taken', false)
            ->orderBy('slot')
            ->get();
    }

    /** @return array<int, array{slot:int, tier:string, label:string, name:?string, points:?int}> */
    private function revealed(Collection $round): array
    {
        $perGift = LoyaltySettings::current()['per_gift'];

        return $round->map(fn (GamePrize $prize) => [
            'slot' => $prize->slot,
            'tier' => $prize->tier,
            'label' => $this->tierLabel($prize->tier),
            'name' => $prize->tier === 'poin' ? null : ClaimService::prizeName($prize),
            'points' => $prize->tier === 'poin' ? $perGift : null,
        ])->values()->all();
    }

    /** Hadiah yang sudah diambil di sesi ini, untuk ringkasan di papan. */
    private function taken(GameSession $session): array
    {
        return $session->prizes()->where('taken', true)->orderBy('updated_at')->get()
            ->map(function (GamePrize $prize) {
                $claim = Claim::where('game_prize_id', $prize->id)->first();

                return [
                    'slot' => $prize->slot,
                    'tier' => $prize->tier,
                    'label' => $this->tierLabel($prize->tier),
                    'name' => $claim?->prize_name,
                    'points' => $prize->tier === 'poin' ? PointLot::where('game_prize_id', $prize->id)->value('points') : null,
                    'claim_code' => $claim?->claim_code,
                    'claim_expires_at' => $claim?->expires_at?->toDateString(),
                ];
            })->values()->all();
    }

    public static function tierLabel(string $tier): string
    {
        return match ($tier) {
            'hadiah_besar' => 'Hadiah Utama',
            'voucher' => 'Voucher',
            'merch' => 'Merch',
            default => 'Poin',
        };
    }

    private function session(string $token): GameSession
    {
        return GameSession::where('brand', config('app.brand'))
            ->where('session_token', $token)
            ->firstOrFail();
    }

    private function lockedSession(string $token): GameSession
    {
        return GameSession::where('brand', config('app.brand'))
            ->where('session_token', $token)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** Alasan sesi tidak bisa memulai ronde baru, atau null kalau bisa. */
    private function playableError(GameSession $session): ?JsonResponse
    {
        if (SessionService::isExpired($session)) {
            return $this->expiredResponse();
        }

        if ($session->chance_used >= $session->chance_total) {
            return response()->json(['error' => 'Kesempatan habis'], 422);
        }

        return null;
    }

    private function expiredResponse(): JsonResponse
    {
        return response()->json(['error' => (new SessionExpiredException)->getMessage()], 422);
    }
}
