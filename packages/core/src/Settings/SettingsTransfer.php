<?php

namespace LoyaltyNew\Core\Settings;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LoyaltyNew\Core\Content\SiteContent;
use LoyaltyNew\Core\Models\PrizeCatalog;
use LoyaltyNew\Core\Models\RewardCatalog;
use LoyaltyNew\Core\Points\LoyaltySettings;
use LoyaltyNew\Core\Prizes\GameControls;
use LoyaltyNew\Core\Prizes\RewardCatalogService;
use LoyaltyNew\Core\Prizes\TierConfig;

/**
 * Export/import setting game satu brand sebagai file JSON (permintaan Fai 2026-10-21,
 * hanya Super Admin). Yang ikut: aturan poin, bobot tier, kendali Gatcha, teks halaman
 * resi, hadiah Gatcha + kuota hadiah utama, dan katalog tukar poin bulan berjalan ke depan.
 *
 * Yang sengaja TIDAK ikut dan tidak pernah ditimpa: data pelanggan, saldo poin, klaim,
 * pemakaian kuota (used_units/quota_used), stok terpakai (stock_used), gambar pop up dan
 * gambar item katalog. Hadiah atau item yang tidak ada di file dinonaktifkan, tidak
 * dihapus, karena klaim lama masih menunjuk ke sana.
 */
final class SettingsTransfer
{
    public const FORMAT = 'loyalty-setting';

    public const VERSION = 1;

    private const TIERS = ['poin', 'voucher', 'merch', 'produk', 'hadiah_besar'];

    private const MAX_PRIZES = 200;

    private const MAX_ITEMS = 300;

    public static function export(string $brand): array
    {
        $caps = DB::table('quota_caps')->where('brand', $brand)->orderBy('id')->get();
        $active = RewardCatalogService::active($brand);
        $from = $active?->period ?? now()->format('Y-m');

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'brand' => $brand,
            'exported_at' => now()->toIso8601String(),
            'points' => LoyaltySettings::current($brand),
            'tiers' => self::tiers($brand),
            'game_controls' => GameControls::current($brand),
            'texts' => array_intersect_key(SiteContent::current($brand), array_flip(['play_title', 'play_lead'])),
            'prizes' => PrizeCatalog::where('brand', $brand)->orderBy('id')->get()->map(fn (PrizeCatalog $p) => [
                'name' => $p->name,
                'tier' => $p->tier,
                'point_value' => $p->point_value,
                'face_value' => $p->face_value,
                'monthly_stock' => $p->monthly_stock,
                'is_capped' => (bool) $p->is_capped,
                'active' => (bool) $p->active,
            ])->values()->all(),
            'quota_caps' => $caps->map(fn ($c) => [
                'prize_name' => $c->prize_name,
                'cap_units' => (int) $c->cap_units,
                'program_start' => substr((string) $c->program_start, 0, 10),
                'program_end' => substr((string) $c->program_end, 0, 10),
                'release_from' => $c->release_from ? substr((string) $c->release_from, 0, 10) : null,
            ])->values()->all(),
            'reward_catalogs' => RewardCatalog::withoutGlobalScopes()->where('brand', $brand)
                ->where('period', '>=', $from)->orderBy('period')->get()
                ->map(fn (RewardCatalog $c) => [
                    'period' => $c->period,
                    'published' => $c->published_at !== null,
                    'items' => $c->items->map(fn ($i) => [
                        'name' => $i->name,
                        'points_cost' => (int) $i->points_cost,
                        'stock' => $i->stock,
                        'active' => (bool) $i->active,
                        'sort' => (int) $i->sort,
                    ])->values()->all(),
                ])->values()->all(),
        ];
    }

    /**
     * Cek isi file dan bandingkan dengan setting sekarang.
     *
     * @return array{data:array,changes:list<string>}
     *
     * @throws InvalidSettingsFile berisi daftar kesalahan yang bisa dibaca admin
     */
    public static function inspect(mixed $file, string $brand): array
    {
        $data = self::normalize($file, $brand);

        return ['data' => $data, 'changes' => self::changes($data, $brand)];
    }

    /** Terapkan file yang sudah lolos inspect(), semua atau tidak sama sekali. */
    public static function apply(array $data, string $brand): void
    {
        // Cek ulang terhadap kondisi terbaru: kuota/stok bisa terpakai sejak pratinjau.
        $data = self::normalize($data, $brand);

        DB::transaction(function () use ($data, $brand) {
            LoyaltySettings::store($data['points'], $brand);
            TierConfig::store($data['tiers'], $brand);
            GameControls::store($data['game_controls'], $brand);
            SiteContent::storeTexts($data['texts']['play_title'], $data['texts']['play_lead'], $brand);

            self::applyPrizes($data, $brand);
            self::applyCatalogs($data['reward_catalogs'], $brand);
        });
    }

    private static function tiers(string $brand): array
    {
        $raw = DB::table('settings')->where('key', 'tier_config:'.$brand)->value('value');
        $stored = $raw ? json_decode($raw, true) : null;

        return is_array($stored) && isset($stored['poin'], $stored['voucher'], $stored['merch'])
            ? ['poin' => (int) $stored['poin'], 'voucher' => (int) $stored['voucher'], 'merch' => (int) $stored['merch'], 'produk' => (int) ($stored['produk'] ?? 0)]
            : TierConfig::default();
    }

    private static function applyPrizes(array $data, string $brand): void
    {
        $caps = collect($data['quota_caps'])->keyBy('prize_name');
        $names = [];

        foreach ($data['prizes'] as $row) {
            $names[] = $row['name'];
            $prize = PrizeCatalog::where('brand', $brand)->where('name', $row['name'])->first();
            $values = [
                'tier' => $row['tier'],
                'point_value' => $row['point_value'],
                'face_value' => $row['face_value'],
                'monthly_stock' => $row['monthly_stock'],
                'is_capped' => $row['is_capped'],
                'active' => $row['active'],
            ];

            if ($prize) {
                // Tier hadiah yang sudah ada tidak diubah: game_prizes lama menyimpan tier itu.
                unset($values['tier']);
                $prize->update($values);
            } else {
                PrizeCatalog::create(['brand' => $brand, 'name' => $row['name'], 'quota_total' => 0, 'quota_used' => 0] + $values);
            }

            if ($cap = $caps->get($row['name'])) {
                $existing = DB::table('quota_caps')->where('brand', $brand)->where('prize_name', $row['name'])->first();
                $fields = [
                    'cap_units' => $cap['cap_units'],
                    'program_start' => $cap['program_start'],
                    'program_end' => $cap['program_end'],
                    'release_from' => $cap['release_from'],
                    'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('quota_caps')->where('id', $existing->id)->update($fields);
                } else {
                    DB::table('quota_caps')->insert($fields + ['brand' => $brand, 'prize_name' => $row['name'], 'used_units' => 0, 'created_at' => now()]);
                }
            }
        }

        PrizeCatalog::where('brand', $brand)->whereNotIn('name', $names)->update(['active' => false]);
    }

    private static function applyCatalogs(array $catalogs, string $brand): void
    {
        foreach ($catalogs as $row) {
            $catalog = RewardCatalog::withoutGlobalScopes()->firstOrCreate(['brand' => $brand, 'period' => $row['period']]);
            $names = [];

            foreach ($row['items'] as $item) {
                $names[] = $item['name'];
                $existing = $catalog->items()->where('name', $item['name'])->first();
                $values = ['points_cost' => $item['points_cost'], 'stock' => $item['stock'], 'active' => $item['active'], 'sort' => $item['sort']];
                $existing ? $existing->update($values) : $catalog->items()->create(['name' => $item['name']] + $values);
            }

            $catalog->items()->whereNotIn('name', $names)->update(['active' => false]);

            // Terbitkan saja; katalog yang sudah terbit tidak ditarik lagi lewat import.
            if ($row['published'] && ! $catalog->published_at) {
                $catalog->update(['published_at' => now()]);
            }
        }
    }

    private static function normalize(mixed $file, string $brand): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        if (! is_array($file) || ($file['format'] ?? null) !== self::FORMAT) {
            throw new InvalidSettingsFile(['Ini bukan file backup setting loyalty. Pakai file hasil tombol Export.']);
        }
        if (($file['version'] ?? null) !== self::VERSION) {
            throw new InvalidSettingsFile(['Versi file tidak dikenali. Export ulang dari panel ini.']);
        }
        if (($file['brand'] ?? null) !== $brand) {
            throw new InvalidSettingsFile(['File ini untuk brand '.ucfirst((string) ($file['brand'] ?? '?')).', bukan '.ucfirst($brand).'. Import di panel brand yang sesuai.']);
        }

        $int = function (mixed $value, string $label, int $min, int $max, bool $nullable = false) use ($fail): ?int {
            if ($value === null && $nullable) {
                return null;
            }
            if (! is_int($value) || $value < $min || $value > $max) {
                $fail("{$label} harus angka bulat {$min} sampai ".number_format($max, 0, ',', '.').'.');

                return $min;
            }

            return $value;
        };
        $date = function (mixed $value, string $label, bool $nullable = false) use ($fail): ?string {
            if ($value === null && $nullable) {
                return null;
            }
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
                $fail("{$label} harus tanggal dengan format TTTT-BB-HH.");

                return null;
            }

            return $value;
        };
        $name = function (mixed $value, string $label) use ($fail): string {
            if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 120) {
                $fail("{$label}: nama wajib diisi, maksimal 120 huruf.");

                return '';
            }

            return trim($value);
        };

        $p = is_array($file['points'] ?? null) ? $file['points'] : [];
        $points = [
            'per_gift' => $int($p['per_gift'] ?? null, 'Poin per kado', 1, 100000),
            'min_redeem' => $int($p['min_redeem'] ?? null, 'Minimal tukar poin', 1, 1000000),
            'validity_months' => $int($p['validity_months'] ?? null, 'Masa berlaku poin (bulan)', 1, 60),
            'per_thousand' => $int($p['per_thousand'] ?? null, 'Poin per Rp1.000', 1, 1000),
            'min_percent' => $int($p['min_percent'] ?? null, 'Persen minimal kado poin', 1, 100),
        ];

        $t = is_array($file['tiers'] ?? null) ? $file['tiers'] : [];
        $tiers = [];
        foreach (['poin', 'voucher', 'merch', 'produk'] as $tier) {
            $tiers[$tier] = $int($t[$tier] ?? 0, "Bobot tier {$tier}", 0, 1000);
        }
        if (array_sum($tiers) <= 0) {
            $fail('Total bobot tier harus lebih dari 0.');
        }

        $g = is_array($file['game_controls'] ?? null) ? $file['game_controls'] : [];
        if (! is_bool($g['paused'] ?? null)) {
            $fail('Kendali Gatcha: isian "paused" harus true atau false.');
        }
        $controls = [
            'paused' => (bool) ($g['paused'] ?? false),
            'big_prize_chance' => $int($g['big_prize_chance'] ?? null, 'Peluang hadiah utama (%)', 0, 100),
            'order_from' => $date($g['order_from'] ?? null, 'Periode mulai', true),
            'order_until' => $date($g['order_until'] ?? null, 'Periode selesai', true),
        ];
        if ($controls['order_from'] && $controls['order_until'] && $controls['order_until'] < $controls['order_from']) {
            $fail('Periode selesai tidak boleh sebelum periode mulai.');
        }

        $tx = is_array($file['texts'] ?? null) ? $file['texts'] : [];
        $texts = [];
        foreach (['play_title' => 'Judul halaman resi', 'play_lead' => 'Keterangan halaman resi'] as $key => $label) {
            $value = $tx[$key] ?? null;
            if (! is_string($value) || mb_strlen($value) > 200) {
                $fail("{$label} harus teks, maksimal 200 huruf.");
                $value = '';
            }
            $texts[$key] = $value;
        }

        $prizes = [];
        $rawPrizes = is_array($file['prizes'] ?? null) ? array_values($file['prizes']) : null;
        if ($rawPrizes === null || count($rawPrizes) > self::MAX_PRIZES) {
            $fail('Daftar hadiah Gatcha tidak terbaca (maksimal '.self::MAX_PRIZES.' hadiah).');
            $rawPrizes = [];
        }
        foreach ($rawPrizes as $i => $row) {
            $row = is_array($row) ? $row : [];
            $label = 'Hadiah #'.($i + 1);
            $prizeName = $name($row['name'] ?? null, $label);
            $label = $prizeName !== '' ? "Hadiah \"{$prizeName}\"" : $label;
            if (isset($prizes[$prizeName])) {
                $fail("{$label} muncul dua kali.");
            }
            $tier = $row['tier'] ?? null;
            if (! in_array($tier, self::TIERS, true)) {
                $fail("{$label}: tier harus salah satu dari ".implode(', ', self::TIERS).'.');
            }
            if (! is_bool($row['active'] ?? null)) {
                $fail("{$label}: isian \"active\" harus true atau false.");
            }
            $prizes[$prizeName] = [
                'name' => $prizeName,
                'tier' => $tier,
                'point_value' => $int($row['point_value'] ?? null, "{$label}: nilai poin", 0, 10000000, true),
                'face_value' => $int($row['face_value'] ?? null, "{$label}: nilai rupiah", 0, 1000000000, true),
                'monthly_stock' => $int($row['monthly_stock'] ?? null, "{$label}: stok bulanan", 0, 1000000, true),
                'is_capped' => $tier === 'hadiah_besar' || ($row['is_capped'] ?? false) === true,
                'active' => (bool) ($row['active'] ?? false),
            ];
        }

        $caps = [];
        $rawCaps = is_array($file['quota_caps'] ?? null) ? array_values($file['quota_caps']) : [];
        foreach ($rawCaps as $i => $row) {
            $row = is_array($row) ? $row : [];
            $capName = $name($row['prize_name'] ?? null, 'Kuota #'.($i + 1));
            $label = "Kuota \"{$capName}\"";
            if (! isset($prizes[$capName])) {
                $fail("{$label}: tidak ada hadiah dengan nama itu di file.");
            }
            $cap = [
                'prize_name' => $capName,
                'cap_units' => $int($row['cap_units'] ?? null, "{$label}: jumlah unit", 1, 100000),
                'program_start' => $date($row['program_start'] ?? null, "{$label}: mulai"),
                'program_end' => $date($row['program_end'] ?? null, "{$label}: selesai"),
                'release_from' => $date($row['release_from'] ?? null, "{$label}: mulai keluar", true),
            ];
            if ($cap['program_start'] && $cap['program_end'] && $cap['program_end'] < $cap['program_start']) {
                $fail("{$label}: tanggal selesai tidak boleh sebelum tanggal mulai.");
            }
            if ($cap['release_from'] && $cap['program_start'] && $cap['program_end']
                && ($cap['release_from'] < $cap['program_start'] || $cap['release_from'] > $cap['program_end'])) {
                $fail("{$label}: mulai keluar harus di antara tanggal mulai dan selesai.");
            }
            $used = (int) DB::table('quota_caps')->where('brand', $brand)->where('prize_name', $capName)->value('used_units');
            if ($cap['cap_units'] < $used) {
                $fail("{$label}: jumlah unit {$cap['cap_units']} di bawah unit yang sudah keluar ({$used}).");
            }
            $caps[$capName] = $cap;
        }
        foreach ($prizes as $prize) {
            if ($prize['is_capped'] && ! isset($caps[$prize['name']])) {
                $fail("Hadiah \"{$prize['name']}\" (hadiah utama) butuh baris kuota di quota_caps.");
            }
        }

        $catalogs = [];
        $active = RewardCatalogService::active($brand);
        $rawCatalogs = is_array($file['reward_catalogs'] ?? null) ? array_values($file['reward_catalogs']) : [];
        foreach ($rawCatalogs as $i => $row) {
            $row = is_array($row) ? $row : [];
            $period = $row['period'] ?? null;
            if (! is_string($period) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                $fail('Katalog #'.($i + 1).': bulan harus format TTTT-BB.');

                continue;
            }
            if ($active && $period < $active->period) {
                $fail("Katalog {$period} sudah lewat. Hanya katalog {$active->period} ke depan yang bisa diubah.");
            }
            if (isset($catalogs[$period])) {
                $fail("Katalog {$period} muncul dua kali.");
            }
            $items = [];
            $rawItems = is_array($row['items'] ?? null) ? array_values($row['items']) : [];
            if (count($rawItems) > self::MAX_ITEMS) {
                $fail("Katalog {$period}: maksimal ".self::MAX_ITEMS.' item.');
                $rawItems = [];
            }
            $existing = RewardCatalog::withoutGlobalScopes()->where('brand', $brand)->where('period', $period)->first();
            foreach ($rawItems as $j => $item) {
                $item = is_array($item) ? $item : [];
                $itemName = $name($item['name'] ?? null, "Katalog {$period} item #".($j + 1));
                $label = "Katalog {$period}, \"{$itemName}\"";
                if (isset($items[$itemName])) {
                    $fail("{$label} muncul dua kali.");
                }
                if (! is_bool($item['active'] ?? null)) {
                    $fail("{$label}: isian \"active\" harus true atau false.");
                }
                $stock = $int($item['stock'] ?? null, "{$label}: stok", 0, 1000000, true);
                $used = $existing ? (int) $existing->items()->where('name', $itemName)->value('stock_used') : 0;
                if ($stock !== null && $stock < $used) {
                    $fail("{$label}: stok {$stock} di bawah yang sudah ditukar ({$used}).");
                }
                $items[$itemName] = [
                    'name' => $itemName,
                    'points_cost' => $int($item['points_cost'] ?? null, "{$label}: harga poin", 1, 10000000),
                    'stock' => $stock,
                    'active' => (bool) ($item['active'] ?? false),
                    'sort' => $int($item['sort'] ?? 0, "{$label}: urutan", 0, 100000),
                ];
            }
            $catalogs[$period] = ['period' => $period, 'published' => ($row['published'] ?? false) === true, 'items' => array_values($items)];
        }

        if ($errors) {
            throw new InvalidSettingsFile(array_values(array_unique($errors)));
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'brand' => $brand,
            'points' => $points,
            'tiers' => $tiers,
            'game_controls' => $controls,
            'texts' => $texts,
            'prizes' => array_values($prizes),
            'quota_caps' => array_values($caps),
            'reward_catalogs' => array_values($catalogs),
        ];
    }

    /** @return list<string> perubahan dalam kalimat yang bisa dibaca admin */
    private static function changes(array $new, string $brand): array
    {
        $old = self::export($brand);
        $lines = [];
        $show = fn ($v) => match (true) {
            $v === null => 'kosong',
            is_bool($v) => $v ? 'ya' : 'tidak',
            default => (string) $v,
        };
        $compare = function (string $section, array $labels) use ($old, $new, $show, &$lines) {
            foreach ($labels as $key => $label) {
                $a = $old[$section][$key] ?? null;
                $b = $new[$section][$key] ?? null;
                if ($a !== $b) {
                    $lines[] = "{$label}: {$show($a)} jadi {$show($b)}";
                }
            }
        };

        $compare('points', ['per_gift' => 'Poin per kado (tanpa nominal order)', 'min_redeem' => 'Minimal tukar poin', 'validity_months' => 'Masa berlaku poin (bulan)', 'per_thousand' => 'Poin per Rp1.000', 'min_percent' => 'Persen minimal kado poin']);
        $compare('tiers', ['poin' => 'Bobot tier poin', 'voucher' => 'Bobot tier voucher', 'merch' => 'Bobot tier merch', 'produk' => 'Bobot tier produk']);
        $compare('game_controls', ['paused' => 'Gatcha dijeda', 'big_prize_chance' => 'Peluang hadiah utama (%)', 'order_from' => 'Periode mulai', 'order_until' => 'Periode selesai']);
        $compare('texts', ['play_title' => 'Judul halaman resi', 'play_lead' => 'Keterangan halaman resi']);

        $oldPrizes = collect($old['prizes'])->keyBy('name');
        $newPrizes = collect($new['prizes'])->keyBy('name');
        foreach ($newPrizes as $prizeName => $prize) {
            $before = $oldPrizes->get($prizeName);
            if (! $before) {
                $lines[] = "Hadiah baru: {$prizeName} ({$prize['tier']})";

                continue;
            }
            foreach (['point_value' => 'nilai poin', 'face_value' => 'nilai rupiah', 'monthly_stock' => 'stok bulanan', 'active' => 'aktif'] as $key => $label) {
                if ($before[$key] !== $prize[$key]) {
                    $lines[] = "Hadiah {$prizeName}, {$label}: {$show($before[$key])} jadi {$show($prize[$key])}";
                }
            }
            if ($before['tier'] !== $prize['tier']) {
                $lines[] = "Hadiah {$prizeName}: tier tetap {$before['tier']} (tier hadiah lama tidak diubah lewat import)";
            }
        }
        foreach ($oldPrizes as $prizeName => $prize) {
            if (! $newPrizes->has($prizeName) && $prize['active']) {
                $lines[] = "Hadiah {$prizeName} tidak ada di file, akan dinonaktifkan";
            }
        }

        $oldCaps = collect($old['quota_caps'])->keyBy('prize_name');
        foreach ($new['quota_caps'] as $cap) {
            $before = $oldCaps->get($cap['prize_name']);
            foreach (['cap_units' => 'jumlah unit', 'program_start' => 'mulai', 'program_end' => 'selesai', 'release_from' => 'mulai keluar'] as $key => $label) {
                if (($before[$key] ?? null) !== $cap[$key]) {
                    $lines[] = "Kuota {$cap['prize_name']}, {$label}: {$show($before[$key] ?? null)} jadi {$show($cap[$key])}";
                }
            }
        }

        $oldCatalogs = collect($old['reward_catalogs'])->keyBy('period');
        foreach ($new['reward_catalogs'] as $catalog) {
            $before = $oldCatalogs->get($catalog['period']);
            if (! $before) {
                $lines[] = "Katalog baru {$catalog['period']}: ".count($catalog['items']).' item'.($catalog['published'] ? ', langsung terbit' : ', belum terbit');

                continue;
            }
            if ($catalog['published'] && ! $before['published']) {
                $lines[] = "Katalog {$catalog['period']} akan diterbitkan";
            }
            if (! $catalog['published'] && $before['published']) {
                $lines[] = "Katalog {$catalog['period']} tetap terbit (import tidak menarik katalog yang sudah terbit)";
            }
            $oldItems = collect($before['items'])->keyBy('name');
            $newItems = collect($catalog['items'])->keyBy('name');
            foreach ($newItems as $itemName => $item) {
                $was = $oldItems->get($itemName);
                if (! $was) {
                    $lines[] = "Katalog {$catalog['period']}: item baru {$itemName} ({$item['points_cost']} poin)";

                    continue;
                }
                foreach (['points_cost' => 'harga poin', 'stock' => 'stok', 'active' => 'aktif', 'sort' => 'urutan'] as $key => $label) {
                    if ($was[$key] !== $item[$key]) {
                        $lines[] = "Katalog {$catalog['period']}, {$itemName}, {$label}: {$show($was[$key])} jadi {$show($item[$key])}";
                    }
                }
            }
            foreach ($oldItems as $itemName => $item) {
                if (! $newItems->has($itemName) && $item['active']) {
                    $lines[] = "Katalog {$catalog['period']}: {$itemName} tidak ada di file, akan dinonaktifkan";
                }
            }
        }

        return $lines;
    }
}
