<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Points\InsufficientPointsException;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Points\PointWallet;
use LoyaltyNew\Core\Prizes\CatalogUnavailableException;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

final class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $catalog = RewardCatalogService::active((string) config('app.brand'));

        return view('customer.catalog', [
            'catalog' => $catalog,
            'items' => $catalog ? $catalog->items()->where('active', true)->get() : collect(),
            'balance' => (new PointWallet($customer))->balance(),
            'minimum' => LoyaltySettings::current()['min_redeem'],
            'redeemBy' => now()->addDays(ClaimService::CATALOG_EXPIRY_DAYS),
        ]);
    }

    public function exchange(Request $request, string $item)
    {
        try {
            $claim = RewardCatalogService::exchange($request->attributes->get('customer'), (int) $item);
        } catch (InsufficientPointsException|CatalogUnavailableException $e) {
            return back()->withErrors(['catalog' => $e->getMessage()]);
        }

        return redirect()->route('rewards.show', $claim->claim_code)
            ->with('ok', 'Poin berhasil ditukar. Redeem hadiah ini sebelum batas waktunya.');
    }
}
