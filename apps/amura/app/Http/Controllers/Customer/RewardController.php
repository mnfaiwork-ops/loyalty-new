<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

final class RewardController extends Controller
{
    private const TABS = [
        'belum' => ['belum_redeem'],
        'proses' => ClaimFlow::IN_PROGRESS,
        'selesai' => ClaimFlow::DONE,
    ];

    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'belum';

        $claims = Claim::where('brand', config('app.brand'))
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::TABS[$tab])
            ->orderByDesc('id')
            ->get();

        return view('customer.rewards', ['claims' => $claims, 'tab' => $tab]);
    }

    public function show(Request $request, string $code)
    {
        $customer = $request->attributes->get('customer');
        $claim = $this->owned($request, $code);

        return view('customer.reward', [
            'claim' => $claim->load('events'),
            'customer' => $customer,
            'waUrl' => WhatsAppLink::url($claim, $customer),
            'waNumber' => WhatsAppLink::number(),
            'waMessage' => WhatsAppLink::message($claim, $customer),
        ]);
    }

    public function redeem(Request $request, string $code)
    {
        $claim = $this->owned($request, $code);

        // Tombol ditekan dua kali: klaim sudah diajukan, cukup tampilkan lagi.
        if ($claim->status === 'diajukan') {
            return redirect()->route('rewards.show', $code);
        }

        try {
            ClaimFlow::request($claim, $request->attributes->get('customer'));
        } catch (ClaimTransitionException $e) {
            return redirect()->route('rewards.show', $code)->withErrors(['redeem' => $e->getMessage()]);
        }

        return redirect()->route('rewards.show', $code)
            ->with('ok', 'Redeem diajukan. Kirim pesan ke WhatsApp CS dari nomor pemenang.');
    }

    private function owned(Request $request, string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))
            ->where('customer_id', $request->attributes->get('customer')->id)
            ->where('claim_code', $code)
            ->firstOrFail();
    }
}
