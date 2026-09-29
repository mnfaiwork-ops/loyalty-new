<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

/** Persetujuan hadiah utama (desain 1t). Klaim masuk ke sini setelah CS mencocokkan nomor. */
final class FinanceController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        $pending = Claim::with(['customer', 'gamePrize'])
            ->where('brand', $brand)
            ->where('status', 'perlu_finance')
            ->orderBy('updated_at')
            ->paginate(20, ['*'], 'pending_page');

        $done = Claim::with(['customer', 'gamePrize'])
            ->where('brand', $brand)
            ->whereNotNull('finance_by')
            ->latest('updated_at')
            ->paginate(20, ['*'], 'done_page');

        return view('admin.finance', ['pending' => $pending, 'done' => $done]);
    }

    public function approve(Request $request, string $code)
    {
        try {
            $claim = Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
            ClaimFlow::approveFinance($claim, $request->user());
        } catch (ModelNotFoundException|ClaimTransitionException) {
            return back()->withErrors(['status' => 'Klaim tidak ditemukan, sudah diproses, atau sudah lewat batas klaim.']);
        }

        return back()->with('ok', 'Hadiah utama disetujui dan masuk Pengiriman.');
    }
}
