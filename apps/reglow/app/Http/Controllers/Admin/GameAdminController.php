<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTierConfigRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Prizes\BigPrizeQuota;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Prizes\PrizeStock;
use LoyaltyNew\Core\Prizes\TierConfig;

final class GameAdminController extends Controller
{
    private const RELEASE_MESSAGES = [
        'program_end.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        'release_from.after_or_equal' => 'Tanggal mulai keluar harus di dalam periode program.',
        'release_from.before_or_equal' => 'Tanggal mulai keluar harus di dalam periode program.',
    ];

    public function index()
    {
        $brand = config('app.brand');

        $catalog = PrizeCatalog::where('brand', $brand)
            ->orderByRaw("case tier when 'hadiah_besar' then 0 when 'produk' then 1 when 'merch' then 2 when 'voucher' then 3 else 4 end")
            ->orderBy('name')
            ->get();

        $caps = DB::table('quota_caps')
            ->where('brand', $brand)
            ->get()
            ->keyBy('prize_name');

        // Pemakaian per hadiah: stok bulan ini untuk voucher/merch/produk, kuota periode untuk hadiah utama.
        $usage = $catalog->mapWithKeys(function (PrizeCatalog $prize) use ($caps) {
            $cap = $caps->get($prize->name);

            return [$prize->id => $cap
                ? ['cap' => $cap, 'unlocked' => BigPrizeQuota::unlocked($cap), 'release' => BigPrizeQuota::releaseFrom($cap)]
                : ['used' => PrizeStock::usedThisMonth((int) $prize->id)]];
        });

        return view('admin.game', [
            'catalog' => $catalog,
            'usage' => $usage,
            'tiers' => TierConfig::current(),
            'controls' => GameControls::current($brand),
        ]);
    }

    public function storeCatalog(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tier' => ['required', 'in:poin,voucher,merch,produk,hadiah_besar'],
            'point_value' => ['nullable', 'integer', 'min:0'],
            'face_value' => ['nullable', 'integer', 'min:0'],
            'quota_total' => ['nullable', 'integer', 'min:0'],
            'monthly_stock' => ['nullable', 'integer', 'min:0'],
            'is_capped' => ['boolean'],
            'cap_units' => ['nullable', 'required_if:is_capped,1', 'required_if:tier,hadiah_besar', 'integer', 'min:1'],
            'program_start' => ['nullable', 'date'],
            'program_end' => ['nullable', 'date', 'after_or_equal:program_start'],
            'release_from' => ['nullable', 'date', 'after_or_equal:program_start', 'before_or_equal:program_end'],
        ], self::RELEASE_MESSAGES);

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
            'monthly_stock' => $isCapped ? null : ($data['monthly_stock'] ?? null),
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
                'release_from' => $data['release_from'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('ok', 'Hadiah ditambahkan');
    }

    /**
     * Ubah hadiah yang sudah ada. Nama tidak bisa diganti karena kuota hadiah
     * utama terikat ke nama itu (quota_caps.prize_name) dan klaim lama menyimpannya.
     */
    public function updateCatalog(Request $request, int $id)
    {
        $prize = PrizeCatalog::where('brand', config('app.brand'))->findOrFail($id);
        $cap = $prize->is_capped ? BigPrizeQuota::cap((string) $prize->brand, $prize->name) : null;

        $data = $request->validate([
            'active' => ['boolean'],
            'face_value' => ['nullable', 'integer', 'min:0'],
            'monthly_stock' => ['nullable', 'integer', 'min:0'],
            'cap_units' => [$cap ? 'required' : 'nullable', 'integer', 'min:'.($cap ? max(1, (int) $cap->used_units) : 1)],
            'program_start' => [$cap ? 'required' : 'nullable', 'date'],
            'program_end' => [$cap ? 'required' : 'nullable', 'date', 'after_or_equal:program_start'],
            'release_from' => ['nullable', 'date', 'after_or_equal:program_start', 'before_or_equal:program_end'],
        ], [
            'cap_units.min' => 'Kuota tidak boleh di bawah unit yang sudah keluar.',
            ...self::RELEASE_MESSAGES,
        ]);

        $prize->update([
            'active' => (bool) ($data['active'] ?? false),
            'face_value' => $data['face_value'] ?? null,
            'monthly_stock' => $prize->is_capped ? null : ($data['monthly_stock'] ?? null),
        ]);

        if ($cap) {
            DB::table('quota_caps')->where('id', $cap->id)->update([
                'cap_units' => $data['cap_units'],
                'program_start' => $data['program_start'],
                'program_end' => $data['program_end'],
                'release_from' => $data['release_from'] ?? null,
                'updated_at' => now(),
            ]);
        }

        return back()->with('ok', "{$prize->name} disimpan.");
    }

    public function updateControls(Request $request)
    {
        $data = $request->validate([
            'paused' => ['boolean'],
            'big_prize_chance' => ['required', 'integer', 'min:0', 'max:100'],
            'order_from' => ['nullable', 'date_format:Y-m-d'],
            'order_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_from'],
        ], [
            'big_prize_chance.*' => 'Peluang hadiah utama diisi 0 sampai 100.',
            'order_until.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
        ]);

        GameControls::store([
            'paused' => (bool) ($data['paused'] ?? false),
            'big_prize_chance' => $data['big_prize_chance'],
            'order_from' => $data['order_from'] ?? null,
            'order_until' => $data['order_until'] ?? null,
        ], (string) config('app.brand'));

        return back()->with('ok', ($data['paused'] ?? false)
            ? 'Gatcha dijeda: semua kado yang dibuka sekarang berisi poin.'
            : 'Kendali Gatcha disimpan.');
    }

    public function updateTiers(UpdateTierConfigRequest $request)
    {
        TierConfig::store($request->validated());

        return back()->with('ok', 'Bobot tier disimpan');
    }
}
