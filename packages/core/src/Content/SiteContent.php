<?php

namespace LoyaltyNew\Core\Content;

use Illuminate\Support\Facades\DB;

/**
 * Teks halaman resi dan pop up promo per brand, diatur Game Admin di /admin/tampilan
 * (permintaan Fai 2026-10-16: judul /play bisa diganti sesuai promo yang berjalan).
 * Disimpan di tabel settings, satu baris per brand.
 */
final class SiteContent
{
    /** Halaman tempat pop up bisa dipasang: kunci => nama di admin. */
    public const PAGES = ['play' => 'Halaman resi (/play)', 'home' => 'Beranda', 'masuk' => 'Halaman masuk (/masuk)'];

    public const DEFAULT_TITLE = 'Paketmu sudah sampai?';

    public const DEFAULT_LEAD = 'Masukkan nomor resi dan nomor HP untuk membuka Gatcha-mu.';

    private const POPUP = ['active' => false, 'image' => null, 'alt' => '', 'link' => null, 'pages' => [], 'version' => 0];

    private static function key(?string $brand): string
    {
        return 'site_content:'.($brand ?? (string) config('app.brand'));
    }

    /** @return array{play_title:string,play_lead:string,popup:array{active:bool,image:?string,alt:string,link:?string,pages:list<string>,version:int}} */
    public static function current(?string $brand = null): array
    {
        $raw = DB::table('settings')->where('key', self::key($brand))->value('value');
        $stored = $raw ? json_decode($raw, true) : [];
        $stored = is_array($stored) ? $stored : [];
        $popup = is_array($stored['popup'] ?? null) ? $stored['popup'] : [];

        return [
            'play_title' => self::text($stored['play_title'] ?? null) ?? self::DEFAULT_TITLE,
            'play_lead' => self::text($stored['play_lead'] ?? null) ?? self::DEFAULT_LEAD,
            'popup' => [
                'active' => (bool) ($popup['active'] ?? false),
                'image' => self::text($popup['image'] ?? null),
                'alt' => (string) ($popup['alt'] ?? ''),
                'link' => self::text($popup['link'] ?? null),
                'pages' => array_values(array_intersect(array_keys(self::PAGES), (array) ($popup['pages'] ?? []))),
                'version' => (int) ($popup['version'] ?? 0),
            ],
        ];
    }

    /** Teks kosong kembali ke teks bawaan. */
    public static function storeTexts(?string $title, ?string $lead, ?string $brand = null): void
    {
        $values = self::current($brand);
        $values['play_title'] = self::text($title) ?? self::DEFAULT_TITLE;
        $values['play_lead'] = self::text($lead) ?? self::DEFAULT_LEAD;
        self::save($values, $brand);
    }

    /**
     * Versi naik setiap kali pop up disimpan, supaya pelanggan yang sudah menutup
     * pop up lama hari ini tetap melihat pop up yang baru.
     */
    public static function storePopup(array $popup, ?string $brand = null): void
    {
        $values = self::current($brand);
        $values['popup'] = [
            'active' => (bool) ($popup['active'] ?? false),
            'image' => self::text($popup['image'] ?? null),
            'alt' => (string) ($popup['alt'] ?? ''),
            'link' => self::text($popup['link'] ?? null),
            'pages' => array_values(array_intersect(array_keys(self::PAGES), (array) ($popup['pages'] ?? []))),
            'version' => $values['popup']['version'] + 1,
        ];
        self::save($values, $brand);
    }

    /** Pop up untuk halaman ini, atau null kalau tidak aktif, tanpa gambar, atau tidak dipasang di halaman ini. */
    public static function popupFor(string $page, ?string $brand = null): ?array
    {
        $popup = self::current($brand)['popup'];

        return $popup['active'] && $popup['image'] && in_array($page, $popup['pages'], true) ? $popup : null;
    }

    private static function save(array $values, ?string $brand): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::key($brand)],
            ['value' => json_encode($values), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
