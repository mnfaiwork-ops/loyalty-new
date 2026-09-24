<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Gacha\Grid;
use LoyaltyNew\Core\Gacha\SessionService;
use LoyaltyNew\Core\Models\GamePrize;
use LoyaltyNew\Core\Models\GameSession;

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
        ]);

        try {
            $session = $this->sessions->open($data['awb'], $data['phone']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['awb' => $e->getMessage()])->withInput();
        }

        return redirect()->route('play.board', $session->session_token);
    }

    public function board(string $token)
    {
        $session = $this->session($token);

        return view('play.board', [
            'session' => $session,
            'prizes' => $session->prizes()->orderBy('slot')->get(),
        ]);
    }

    public function pick(Request $request, string $token): JsonResponse
    {
        $session = $this->session($token);

        if ($session->chance_used >= $session->chance_total) {
            return response()->json(['error' => 'Kesempatan habis'], 422);
        }

        $data = $request->validate([
            'slots' => ['required', 'array', 'size:3'],
            'slots.*' => ['required', 'integer', 'distinct'],
        ]);

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
    }

    public function reveal(string $token): JsonResponse
    {
        $session = $this->session($token);
        $round = $this->round($session);

        if ($round->count() !== Grid::PICKS) {
            return response()->json(['error' => 'Belum memilih 3 slot'], 422);
        }

        $session->prizes()->whereIn('id', $round->pluck('id'))->update(['opened' => true]);

        return response()->json(['ok' => true, 'revealed' => $this->revealed($round)]);
    }

    public function take(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'integer'],
        ]);

        return DB::transaction(function () use ($token, $data) {
            $session = GameSession::where('brand', config('app.brand'))
                ->where('session_token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            $round = $this->round($session);
            $chosen = $round->firstWhere('slot', $data['slot']);

            if (! $chosen) {
                return response()->json(['error' => 'Slot belum dipilih'], 422);
            }

            $session->prizes()->whereIn('id', $round->pluck('id'))->update(['opened' => true]);

            if (! $this->sessions->consumeChance($session)) {
                return response()->json(['error' => 'Kesempatan habis'], 422);
            }

            $chosen->update(['taken' => true, 'opened' => true]);

            $others = $round->pluck('id')->reject(fn ($id) => $id === $chosen->id);
            $session->prizes()->whereIn('id', $others)->update(['picked' => false]);

            return response()->json([
                'ok' => true,
                'revealed' => $this->revealed($round),
                'chosen' => $chosen->slot,
            ]);
        });
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

    /** @return array<int, array{slot:int, tier:string, label:string}> */
    private function revealed(Collection $round): array
    {
        return $round->map(fn (GamePrize $prize) => [
            'slot' => $prize->slot,
            'tier' => $prize->tier,
            'label' => $this->tierLabel($prize->tier),
        ])->values()->all();
    }

    private function tierLabel(string $tier): string
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
}
