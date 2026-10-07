<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use LoyaltyNew\Core\Customers\CustomerPin;
use LoyaltyNew\Core\Gacha\AttemptLimiter;
use LoyaltyNew\Core\Gate\AwbMatcher;
use LoyaltyNew\Core\Models\Customer;

/** CS mencari pelanggan dan mereset PIN yang terlupa (spec PIN 2026-10-07, desain 3l). */
final class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $digits = preg_replace('/\D+/', '', $q);

        $customers = $digits === ''
            ? collect()
            : Customer::with('pinResetBy')
                ->where('phone', 'like', '%'.AwbMatcher::normalizePhone($digits).'%')
                ->orderBy('phone')
                ->limit(20)
                ->get();

        $brand = (string) config('app.brand');
        $themes = config('play.themes');

        return view('admin.customers', [
            'q' => $q,
            'customers' => $customers,
            'temporary' => session('temporary_pin'),
            'brand' => $brand,
            'brandName' => $themes[$brand]['name'] ?? ucfirst($brand),
            // Tab brand: brand ini selalu ada, brand lain hanya kalau alamat panelnya diisi.
            'brandTabs' => collect($themes)->map(fn ($theme, $key) => [
                'name' => $theme['name'],
                'url' => $key === $brand
                    ? null
                    : (($base = config('play.admin_urls.'.$key)) ? rtrim($base, '/').'/admin/customers'.($q !== '' ? '?q='.rawurlencode($q) : '') : null),
            ])->filter(fn ($tab, $key) => $key === $brand || $tab['url'] !== null),
        ]);
    }

    public function resetPin(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        // Belum punya PIN berarti belum pernah masuk; pelanggan diarahkan masuk dengan resi.
        if ($customer->pin_hash === null) {
            return back()->withErrors(['pin' => 'Pelanggan ini belum punya PIN. Arahkan masuk dengan resi untuk membuat PIN.']);
        }

        $pin = CustomerPin::issueTemporary($customer, $request->user());

        // Kunci PIN salah dibuka, supaya pelanggan bisa langsung memakai PIN sementara.
        AttemptLimiter::fromConfig((string) config('app.brand'))->clearPin($customer->phone);

        return redirect()->route('admin.customers', ['q' => $customer->phone])->with('temporary_pin', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'pin' => $pin,
            'wa' => WhatsAppLink::temporaryPin($customer, $pin),
            'message' => WhatsAppLink::temporaryPinMessage($customer, $pin),
            'by' => $request->user()->name,
            'at' => now()->format('d/m/Y H.i'),
        ]);
    }
}
