<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RememberedCustomer;
use App\Support\PendingCustomer;
use App\Support\PhoneInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Gacha\Grid;
use LoyaltyNew\Core\Gacha\OutsideProgramException;
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
    private const NOT_OWNER = 'Papan ini hanya bisa dibuka dari HP pemiliknya. Masuk dengan nomor resi dan nomor HP.';

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

        $data['phone'] = PhoneInput::normalize($data['phone']);

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

            return $this->throughPin($request, $customer, route('home'),
                'Masa main untuk paket ini sudah berakhir. Poin dan hadiahmu tetap bisa dilihat di sini.');
        } catch (OutsideProgramException $e) {
            // Resi + HP benar, ordernya saja di luar periode: bukan percobaan gagal.
            $limiter->clear($data['awb'], $data['phone']);

            return back()->withErrors(['awb' => $e->getMessage()])->withInput();
        } catch (\RuntimeException $e) {
            $limiter->recordFailure($data['awb'], $data['phone']);

            return back()->withErrors(['awb' => $e->getMessage()])->withInput();
        }

        $limiter->clear($data['awb'], $data['phone']);

        $customer = Customer::findOrFail($session->customer_id);
        $target = $session->chance_used < $session->chance_total
            ? route('play.board', $session->session_token)
            : route('home');

        return $this->throughPin($request, $customer, $target);
    }

    /**
     * Resi + HP sudah cocok. Pelanggan baru membuat PIN, pelanggan lama
     * memasukkan PIN-nya, lalu lanjut ke tujuan (spec PIN 2026-10-07).
     */
    private function throughPin(Request $request, Customer $customer, string $next, ?string $notice = null)
    {
        $customer->refresh();

        // Perangkat ini sudah diingat untuk pelanggan yang sama, jadi PIN tidak ditanya lagi.
        $remembered = RememberedCustomer::resolve($request);
        if ($remembered?->id === $customer->id) {
            return redirect($next)->with('ok', $notice)->withCookie(RememberedCustomer::cookieFor($customer));
        }

        $isNew = $customer->pin_hash === null;
        PendingCustomer::put($request, $customer, $next, canSet: $isNew, notice: $notice);

        return redirect()->route($isNew ? 'pin.create' : 'pin.show');
    }

    public function board(Request $request, string $token)
    {
        $session = $this->session($token);

        if (! $this->owns($request, $session)) {
            return redirect()->route('play.form')
                ->withErrors(['awb' => self::NOT_OWNER]);
        }

        if (SessionService::isExpired($session)) {
            return redirect()->route('play.form')
                ->withErrors(['awb' => (new SessionExpiredException)->getMessage()]);
        }

        // Sesi lama dari sebelum papan per kesempatan belum punya papan kesempatan 2.
        DB::transaction(fn () => $this->sessions->ensureRound($this->lockedSession($token)));

        // Ronde yang belum selesai (mis. halaman di-refresh) ditampilkan lagi,
        // supaya pelanggan bisa melanjutkan, bukan terjebak di kotak yang terkunci.
        $round = $this->round($session);
        $revealed = $round->count() === Grid::PICKS && $round->every(fn (GamePrize $p) => $p->opened)
            ? $this->revealed($round)
            : [];

        return view('play.board', [
            'session' => $session,
            'prizes' => $session->boardPrizes()->orderBy('slot')->get(),
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
        return DB::transaction(function () use ($request, $token, $data) {
            $session = $this->lockedSession($token);

            if (! $this->owns($request, $session)) {
                return $this->notOwnerResponse();
            }

            if ($error = $this->playableError($session)) {
                return $error;
            }

            $this->sessions->ensureRound($session);

            foreach ($data['slots'] as $slot) {
                $available = $slot >= 1 && $slot <= Grid::SLOTS
                    && $session->boardPrizes()
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

            $session->boardPrizes()->whereIn('slot', $data['slots'])->update(['picked' => true]);

            return response()->json(['ok' => true]);
        });
    }

    public function reveal(Request $request, string $token): JsonResponse
    {
        return DB::transaction(function () use ($request, $token) {
            $session = $this->lockedSession($token);

            if (! $this->owns($request, $session)) {
                return $this->notOwnerResponse();
            }

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

        return DB::transaction(function () use ($request, $token, $data) {
            $session = $this->lockedSession($token);

            if (! $this->owns($request, $session)) {
                return $this->notOwnerResponse();
            }

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

        // Kesempatan berikutnya langsung disiapkan dengan 24 kado baru.
        $session = $session->fresh();
        $this->sessions->ensureRound($session);

        return [
            'ok' => true,
            'revealed' => $this->revealed($round),
            'chosen' => $chosen->slot,
            'prize_name' => $claim?->prize_name,
            'points' => $award['type'] === 'points' ? $award['points'] : null,
            'balance' => $award['type'] === 'points' ? $award['balance'] : null,
            'claim_code' => $claim?->claim_code,
            'claim_expires_at' => $claim?->expires_at->toDateString(),
            'chance_left' => max(0, $session->chance_total - $session->chance_used),
            'chance_total' => $session->chance_total,
        ];
    }

    /** Slot ronde aktif: sudah dipilih tapi belum disimpan. */
    private function round(GameSession $session): Collection
    {
        return $session->boardPrizes()
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
            'points' => $prize->tier === 'poin' ? ($prize->points ?? $perGift) : null,
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

    /**
     * Papan hanya bisa dimainkan dari perangkat pemiliknya yang sudah lolos PIN,
     * jadi link papan yang dibagikan ke orang lain tidak bisa dipakai.
     */
    private function owns(Request $request, GameSession $session): bool
    {
        return RememberedCustomer::resolve($request)?->id === (int) $session->customer_id;
    }

    private function notOwnerResponse(): JsonResponse
    {
        return response()->json(['error' => self::NOT_OWNER], 403);
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
