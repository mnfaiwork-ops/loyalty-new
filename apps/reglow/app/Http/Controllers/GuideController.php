<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\ClaimService;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use LoyaltyNew\Core\Prizes\Segment;

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
            'bigPrizeRepeatOnly' => $this->bigPrizeRepeatOnly($brand),
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

    /** Semua hadiah utama aktif hanya untuk pelanggan lama: kalimat "mulai pesanan kedua" boleh tampil. */
    private function bigPrizeRepeatOnly(string $brand): bool
    {
        $main = PrizeCatalog::where('brand', $brand)->where('tier', 'hadiah_besar')->where('active', true)->get();

        return $main->isNotEmpty() && $main->every(fn (PrizeCatalog $prize) => Segment::ofPrize($prize) === Segment::LAMA);
    }

    /** @return list<array{name:string,note:string,main:bool}> */
    private function prizes(string $brand): array
    {
        $rows = [];
        $active = PrizeCatalog::where('brand', $brand)->where('active', true)->orderBy('id')->get(['name', 'tier', 'segment', 'face_value']);

        foreach (self::TIER_ORDER as $tier) {
            $items = $active->where('tier', $tier);
            // Hadiah utama dari nilai terkecil, jadi Philips berurutan dan Emas di akhir.
            if ($tier === 'hadiah_besar') {
                $items = $items->sortBy(fn (PrizeCatalog $item) => (int) $item->face_value);
            }
            if ($items->isEmpty()) {
                continue;
            }
            if ($tier === 'poin') {
                $rows[] = ['name' => 'Poin', 'note' => 'nilainya ikut nilai belanja', 'main' => false];

                continue;
            }
            foreach ($items->unique('name') as $item) {
                // Aturan jenis pelanggan tidak ditampilkan, kecuali hadiah utama untuk pelanggan
                // lama yang memang disebut di copy ("mulai pesanan kedua").
                $main = $tier === 'hadiah_besar';
                // Kalau semua hadiah utama untuk pelanggan lama, kalimat di atas daftar sudah menyebutnya.
                $repeatOnly = Segment::ofPrize($item) === Segment::LAMA && ! $this->bigPrizeRepeatOnly($brand);
                $note = $main ? ($repeatOnly ? 'Hadiah utama, mulai pesanan kedua' : 'Hadiah utama') : '';
                $rows[] = ['name' => $item->name, 'note' => $note, 'main' => $main];
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
