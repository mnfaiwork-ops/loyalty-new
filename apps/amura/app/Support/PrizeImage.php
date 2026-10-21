<?php

namespace App\Support;

use Illuminate\Support\Str;
use LoyaltyNew\Core\Models\PrizeCatalog;

/**
 * Foto hadiah untuk gambar share story (permintaan Fai 2026-10-21). Foto unggahan Game Admin
 * dipakai lebih dulu; kalau belum ada, foto bawaan dari POS di public/img/hadiah/<brand>/.
 * Null = gambar story memakai ilustrasi kado.
 */
final class PrizeImage
{
    public static function url(?PrizeCatalog $prize): ?string
    {
        if (! $prize) {
            return null;
        }

        if ($prize->image) {
            return asset('storage/'.$prize->image);
        }

        $default = self::defaultPath($prize);

        return is_file(public_path($default)) ? asset($default) : null;
    }

    public static function defaultPath(PrizeCatalog $prize): string
    {
        return 'img/hadiah/'.$prize->brand.'/'.Str::slug($prize->name).'.webp';
    }
}
