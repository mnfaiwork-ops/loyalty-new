<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\Claim;

final class FinanceController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        $pending = Claim::with(['gamePrize', 'gameSession'])
            ->where('status', 'perlu_finance')
            ->whereHas('gameSession', fn ($query) => $query->where('brand', $brand))
            ->whereHas('gamePrize', fn ($query) => $query->where('tier', 'hadiah_besar'))
            ->latest()
            ->paginate(20, ['*'], 'pending_page');

        $done = Claim::with(['gamePrize', 'gameSession'])
            ->where('status', 'selesai')
            ->whereHas('gameSession', fn ($query) => $query->where('brand', $brand))
            ->whereHas('gamePrize', fn ($query) => $query->where('tier', 'hadiah_besar'))
            ->latest()
            ->paginate(20, ['*'], 'done_page');

        return view('admin.finance', ['pending' => $pending, 'done' => $done]);
    }

    public function approve(Request $request, string $code)
    {
        $brand = config('app.brand');

        try {
            DB::transaction(function () use ($code, $brand, $request) {
                $claim = Claim::whereHas('gameSession', fn ($query) => $query->where('brand', $brand))
                    ->where('claim_code', $code)
                    ->where('status', 'perlu_finance')
                    ->where('expires_at', '>=', now())
                    ->lockForUpdate()
                    ->firstOrFail();

                $claim->update([
                    'status' => 'selesai',
                    'finance_by' => $request->user()->id,
                    'claimed_at' => now(),
                ]);
            });
        } catch (ModelNotFoundException) {
            return back()->withErrors(['status' => 'Klaim tidak ditemukan, sudah diproses, atau sudah lewat batas klaim.']);
        }

        return back()->with('ok', 'Hadiah besar disetujui');
    }
}
