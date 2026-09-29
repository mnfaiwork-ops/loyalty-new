<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

/** Antrian redeem WhatsApp (desain 3i): CS mencocokkan nomor pengirim dengan nomor pemenang. */
final class CsClaimController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $claims = Claim::with('customer')
            ->where('brand', config('app.brand'))
            ->when($q !== '', fn ($query) => $query->where('claim_code', 'ilike', "%{$q}%"))
            ->when($q === '', fn ($query) => $query->where('status', 'diajukan')->orderBy('redeem_requested_at'))
            ->when($q !== '', fn ($query) => $query->latest('id'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.claims', ['claims' => $claims, 'q' => $q]);
    }

    public function show(string $code)
    {
        return view('admin.claim', ['claim' => $this->find($code)->load(['customer', 'events', 'gamePrize'])]);
    }

    public function confirm(Request $request, string $code)
    {
        $data = $this->validated($request);

        try {
            $claim = ClaimFlow::confirm($this->find($code), $request->user(), $data['sender_phone'], $data['note'] ?? null);
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        $next = $claim->status === 'perlu_finance' ? 'diteruskan ke Finance' : 'masuk Pengiriman';

        return redirect()->route('admin.claims')->with('ok', "Klaim {$claim->claim_code} dikonfirmasi dan {$next}.");
    }

    public function reject(Request $request, string $code)
    {
        $data = $this->validated($request);

        try {
            $claim = ClaimFlow::reject($this->find($code), $request->user(), $data['sender_phone'], $data['note'] ?? null);
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.claims')->with('ok', "Klaim {$claim->claim_code} ditolak dan kembali ke daftar pelanggan.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'sender_phone' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['sender_phone.required' => 'Isi nomor pengirim WhatsApp dulu.']);
    }

    private function find(string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
    }
}
