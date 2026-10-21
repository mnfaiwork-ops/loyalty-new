<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WhatsAppLink;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Points\LoyaltySettings;

/**
 * Daftar harian untuk BA Team Repeat (alur funnel 2026-10-20): siapa yang perlu dihubungi
 * hari ini berdasarkan data app loyalty. Diunduh sebagai CSV, lalu dicocokkan ke BA
 * pemegang customer lewat nomor HP di FairTrack.
 */
final class DailyListController extends Controller
{
    /** Hadiah yang hangus dalam sekian hari ke depan masuk daftar pengingat. */
    public const EXPIRING_DAYS = 4;

    private const LISTS = [
        'belum-main' => 'Sudah masuk, kesempatan belum habis',
        'hadiah-hampir-hangus' => 'Hadiah belum di-redeem, hangus dalam '.self::EXPIRING_DAYS.' hari',
        'saldo-cukup' => 'Saldo poin cukup untuk ditukar',
    ];

    /** Kolom CSV per daftar; header tetap ditulis walau daftar kosong. */
    private const HEADERS = [
        'belum-main' => ['no_hp', 'nama', 'resi', 'sisa_kesempatan', 'nilai_order', 'masuk_pertama', 'berlaku_sampai'],
        'hadiah-hampir-hangus' => ['no_hp', 'nama', 'kode_hadiah', 'hadiah', 'didapat', 'hangus', 'sisa_hari'],
        'saldo-cukup' => ['no_hp', 'nama', 'saldo_poin', 'poin_terdekat_hangus'],
    ];

    public function index()
    {
        $counts = collect(array_keys(self::LISTS))->mapWithKeys(fn ($key) => [$key => $this->rows($key)->count()]);

        return view('admin.daily-lists', [
            'lists' => self::LISTS,
            'counts' => $counts,
            'minRedeem' => LoyaltySettings::current()['min_redeem'],
            'expiringDays' => self::EXPIRING_DAYS,
        ]);
    }

    public function download(string $list)
    {
        abort_unless(isset(self::LISTS[$list]), 404);

        $rows = $this->rows($list);
        $name = 'daftar-'.$list.'-'.config('app.brand').'-'.now()->format('Ymd').'.csv';

        $headers = self::HEADERS[$list];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca huruf dengan benar
            fputcsv($out, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), escape: '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int,array<string,mixed>> */
    private function rows(string $list): Collection
    {
        $brand = (string) config('app.brand');
        $date = fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i') : '';
        $contact = fn ($row) => [
            'no_hp' => WhatsAppLink::international((string) $row->phone),
            'nama' => (string) $row->name,
        ];

        return match ($list) {
            'belum-main' => DB::table('game_sessions as s')
                ->join('customers as c', 'c.id', '=', 's.customer_id')
                ->where('s.brand', $brand)
                ->whereColumn('s.chance_used', '<', 's.chance_total')
                ->where('s.expires_at', '>', now())
                ->orderBy('s.created_at')
                ->get(['c.phone', 'c.name', 's.awb', 's.chance_total', 's.chance_used', 's.order_amount', 's.created_at', 's.expires_at'])
                ->map(fn ($r) => $contact($r) + [
                    'resi' => (string) $r->awb,
                    'sisa_kesempatan' => (int) $r->chance_total - (int) $r->chance_used,
                    'nilai_order' => $r->order_amount,
                    'masuk_pertama' => $date($r->created_at),
                    'berlaku_sampai' => $date($r->expires_at),
                ]),
            'hadiah-hampir-hangus' => DB::table('claims as k')
                ->join('customers as c', 'c.id', '=', 'k.customer_id')
                ->where('k.brand', $brand)
                ->where('k.status', 'belum_redeem')
                ->whereBetween('k.expires_at', [now(), now()->addDays(self::EXPIRING_DAYS)])
                ->orderBy('k.expires_at')
                ->get(['c.phone', 'c.name', 'k.claim_code', 'k.prize_name', 'k.created_at', 'k.expires_at'])
                ->map(fn ($r) => $contact($r) + [
                    'kode_hadiah' => (string) $r->claim_code,
                    'hadiah' => (string) $r->prize_name,
                    'didapat' => $date($r->created_at),
                    'hangus' => $date($r->expires_at),
                    'sisa_hari' => (int) ceil(now()->diffInHours(Carbon::parse($r->expires_at)) / 24),
                ]),
            'saldo-cukup' => DB::table('point_lots as l')
                ->join('customers as c', 'c.id', '=', 'l.customer_id')
                ->where('l.brand', $brand)
                ->where('l.expires_at', '>', now())
                ->groupBy('c.id', 'c.phone', 'c.name')
                ->havingRaw('SUM(l.remaining) >= ?', [LoyaltySettings::current()['min_redeem']])
                ->orderByRaw('SUM(l.remaining) DESC')
                ->get(['c.phone', 'c.name', DB::raw('SUM(l.remaining) as saldo'), DB::raw('MIN(CASE WHEN l.remaining > 0 THEN l.expires_at END) as hangus_terdekat')])
                ->map(fn ($r) => $contact($r) + [
                    'saldo_poin' => (int) $r->saldo,
                    'poin_terdekat_hangus' => $date($r->hangus_terdekat),
                ]),
        };
    }

    /** Cegah isi sel dibaca sebagai rumus di Excel/Sheets (nama pelanggan bisa diawali =). */
    private static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
