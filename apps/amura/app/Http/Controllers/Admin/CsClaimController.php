<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimService;

final class CsClaimController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $claims = Claim::with(['gamePrize', 'gameSession'])
            ->whereHas('gameSession', fn ($query) => $query->where('brand', config('app.brand')))
            ->when($q, fn ($query) => $query->where('claim_code', 'like', "%{$q}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.claims', ['claims' => $claims, 'q' => $q]);
    }

    public function resolve(Request $request, string $code)
    {
        $claim = Claim::with('gamePrize')
            ->whereHas('gameSession', fn ($query) => $query->where('brand', config('app.brand')))
            ->where('claim_code', $code)
            ->firstOrFail();

        $needsFinance = $claim->gamePrize?->tier === 'hadiah_besar' || $claim->status === 'perlu_finance';
        $allowed = $needsFinance ? ['hangus'] : ['diproses', 'selesai', 'hangus'];

        $data = $request->validate(['status' => ['required', 'in:'.implode(',', $allowed)]]);

        // Dikunci supaya dua klik bersamaan tidak sama-sama lolos cek status
        // (dan tidak mengembalikan kuota hadiah besar dua kali).
        $error = DB::transaction(function () use ($claim, $data, $request) {
            $claim = Claim::with('gamePrize')->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            // Klaim yang sudah selesai atau hangus bersifat final.
            if (! in_array($claim->status, ClaimService::OPEN_STATUSES, true)) {
                return "Klaim {$claim->claim_code} sudah {$claim->status}, tidak bisa diubah.";
            }

            // Lewat batas klaim: hanya boleh ditandai hangus (spec §2).
            if ($claim->expires_at?->isPast() && $data['status'] !== 'hangus') {
                return "Klaim {$claim->claim_code} sudah lewat batas klaim, hanya bisa ditandai hangus.";
            }

            $claim->update([
                'status' => $data['status'],
                'handled_by' => $request->user()->id,
                'claimed_at' => now(),
            ]);

            if ($data['status'] === 'hangus') {
                ClaimService::releaseBigPrizeQuota($claim, (string) config('app.brand'));
            }

            return null;
        });

        if ($error) {
            return back()->withErrors(['status' => $error]);
        }

        return back()->with('ok', 'Klaim diperbarui');
    }
}
