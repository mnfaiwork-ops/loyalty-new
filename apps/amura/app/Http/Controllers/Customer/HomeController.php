<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberedCustomer;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Models\Claim;
use LoyaltyNew\Core\Models\GameSession;
use LoyaltyNew\Core\Points\PointWallet;

final class HomeController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->attributes->get('customer');
        $wallet = new PointWallet($customer);
        $claims = Claim::where('brand', config('app.brand'))->where('customer_id', $customer->id);

        $sessions = GameSession::where('customer_id', $customer->id)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();
        $playable = $sessions->first(fn (GameSession $s) => $s->chance_used < $s->chance_total);

        return view('customer.home', [
            'customer' => $customer,
            'balance' => $wallet->balance(),
            'expiring' => $wallet->nextExpiring(),
            'open' => (clone $claims)->where('status', 'belum_redeem')->where('expires_at', '>', now())->orderBy('expires_at')->get(),
            'recent' => (clone $claims)->latest('id')->limit(3)->get(),
            'session' => $playable,
            'chances' => $sessions->sum(fn (GameSession $s) => max(0, $s->chance_total - $s->chance_used)),
        ]);
    }

    public function logout()
    {
        return redirect()->route('play.form')->withoutCookie(RememberedCustomer::COOKIE);
    }
}
