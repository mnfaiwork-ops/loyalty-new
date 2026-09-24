<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTierConfigRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\QuotaGuard;
use LoyaltyNew\Core\Prizes\TierConfig;

final class GameAdminController extends Controller
{
    public function index()
    {
        $brand = config('app.brand');

        $catalog = PrizeCatalog::where('brand', $brand)
            ->latest()
            ->paginate(20);

        $caps = DB::table('quota_caps')
            ->where('brand', $brand)
            ->get()
            ->keyBy('prize_name');

        $remaining = $catalog->getCollection()->mapWithKeys(function (PrizeCatalog $prize) use ($caps) {
            $cap = $caps->get($prize->name);

            return [$prize->id => $cap
                ? QuotaGuard::remaining((int) $cap->cap_units, (int) $cap->used_units)
                : null];
        });

        return view('admin.game', [
            'catalog' => $catalog,
            'tiers' => TierConfig::current(),
            'remaining' => $remaining,
        ]);
    }

    public function storeCatalog(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tier' => ['required', 'in:poin,voucher,merch,hadiah_besar'],
            'point_value' => ['nullable', 'integer', 'min:0'],
            'face_value' => ['nullable', 'integer', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:0'],
            'is_capped' => ['boolean'],
            'cap_units' => ['nullable', 'required_if:is_capped,1', 'required_if:tier,hadiah_besar', 'integer', 'min:1'],
            'program_start' => ['nullable', 'date'],
            'program_end' => ['nullable', 'date', 'after_or_equal:program_start'],
        ]);

        $brand = config('app.brand');

        if ($data['tier'] === 'hadiah_besar') {
            $data['is_capped'] = true;
        }

        $isCapped = (bool) ($data['is_capped'] ?? false);

        $catalog = PrizeCatalog::create([
            'brand' => $brand,
            'name' => $data['name'],
            'tier' => $data['tier'],
            'point_value' => $data['point_value'] ?? null,
            'face_value' => $data['face_value'] ?? null,
            'quota_total' => $data['quota_total'] ?? null,
            'is_capped' => $isCapped,
            'active' => true,
        ]);

        if ($isCapped) {
            DB::table('quota_caps')->insert([
                'brand' => $brand,
                'prize_name' => $catalog->name,
                'cap_units' => $data['cap_units'],
                'used_units' => 0,
                'program_start' => $data['program_start'] ?? now()->toDateString(),
                'program_end' => $data['program_end'] ?? now()->addMonth()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('ok', 'Hadiah ditambahkan');
    }

    public function updateTiers(UpdateTierConfigRequest $request)
    {
        TierConfig::store($request->validated());

        return back()->with('ok', 'Bobot tier disimpan');
    }
}
