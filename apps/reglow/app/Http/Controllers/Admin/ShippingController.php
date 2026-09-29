<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Prizes\ClaimFlow;
use LoyaltyNew\Core\Prizes\ClaimTransitionException;

final class ShippingController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        return view('admin.shipping', [
            'packing' => Claim::with('customer')->where('brand', $brand)->where('status', 'diproses')->orderBy('updated_at')->get(),
            'shipped' => Claim::with('customer')->where('brand', $brand)->where('status', 'dikirim')->orderBy('updated_at')->get(),
        ]);
    }

    public function ship(Request $request, string $code)
    {
        $data = $request->validate(['shipping_awb' => ['required', 'string', 'max:64']], [
            'shipping_awb.required' => 'Isi resi pengiriman hadiah dulu.',
        ]);

        return $this->run(fn () => ClaimFlow::ship($this->find($code), $request->user(), $data['shipping_awb']), 'dikirim');
    }

    public function deliver(Request $request, string $code)
    {
        return $this->run(fn () => ClaimFlow::deliver($this->find($code), $request->user()), 'ditandai diterima');
    }

    private function run(callable $action, string $done)
    {
        try {
            $claim = $action();
        } catch (ClaimTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.shipping')->with('ok', "Klaim {$claim->claim_code} {$done}.");
    }

    private function find(string $code): Claim
    {
        return Claim::where('brand', config('app.brand'))->where('claim_code', $code)->firstOrFail();
    }
}
