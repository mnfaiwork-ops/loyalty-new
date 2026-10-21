<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Prizes\RewardCatalogService;

/**
 * Landing page cara main (/cara-main, rev 3 kanvas 2026-10-21). Publik, tanpa login.
 * Angka dan daftar hadiah dibaca dari setting yang sama dengan game, jadi halaman
 * ini ikut berubah begitu Game Admin mengganti poin, periode, hadiah, atau katalog.
 */
final class GuideController extends Controller
{
    /** Urutan tampil per tier; tier poin digabung jadi satu baris. */
    private const TIER_ORDER = ['poin', 'voucher', 'produk', 'merch', 'hadiah_besar'];

    public function show()
    {
        $brand = (string) config('app.brand');
        $points = LoyaltySettings::current($brand);
        $controls = GameControls::current($brand);
        $catalog = RewardCatalogService::active($brand);

        return view('guide', [
            'shots' => fn (string $name) => asset("img/cara-main/{$brand}/{$name}.webp"),
            'prizes' => $this->prizes($brand),
            'catalog' => $catalog,
            'catalogItems' => $catalog ? $catalog->items()->where('active', true)->get() : collect(),
            'catalogMonth' => $catalog ? Carbon::createFromFormat('Y-m-d', $catalog->period.'-01')->translatedFormat('F Y') : null,
            'minRedeem' => $points['min_redeem'],
            'validityMonths' => $points['validity_months'],
            'period' => $this->period($controls['order_from'], $controls['order_until']),
            'startsOn' => $controls['order_from'] ? Carbon::parse($controls['order_from'])->translatedFormat('j F Y') : null,
            'redeemDays' => ClaimService::expiryFor('voucher'),
            'bigPrizeDays' => ClaimService::expiryFor('hadiah_besar'),
            'catalogDays' => ClaimService::CATALOG_EXPIRY_DAYS,
        ]);
    }

    /** @return list<array{name:string,note:string,main:bool}> */
    private function prizes(string $brand): array
    {
        $rows = [];
        $active = PrizeCatalog::where('brand', $brand)->where('active', true)->orderBy('id')->get(['name', 'tier']);

        foreach (self::TIER_ORDER as $tier) {
            $items = $active->where('tier', $tier);
            if ($items->isEmpty()) {
                continue;
            }
            if ($tier === 'poin') {
                $rows[] = ['name' => 'Poin', 'note' => 'nilainya ikut nilai belanja', 'main' => false];

                continue;
            }
            foreach ($items->unique('name') as $item) {
                $main = $tier === 'hadiah_besar';
                $rows[] = ['name' => $item->name, 'note' => $main ? 'Hadiah utama, mulai pesanan kedua' : '', 'main' => $main];
            }
        }

        return $rows;
    }

    private function period(?string $from, ?string $until): ?string
    {
        if (! $from && ! $until) {
            return null;
        }
        if ($from && ! $until) {
            return 'mulai '.Carbon::parse($from)->translatedFormat('j F Y');
        }
        if (! $from) {
            return 'sampai '.Carbon::parse($until)->translatedFormat('j F Y');
        }

        $start = Carbon::parse($from);
        $end = Carbon::parse($until);

        return $start->translatedFormat($start->year === $end->year ? 'j F' : 'j F Y').' sampai '.$end->translatedFormat('j F Y');
    }
}
