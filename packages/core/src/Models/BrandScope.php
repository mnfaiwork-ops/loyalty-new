<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Batasi query model ke brand aplikasi (config app.brand).
 *
 * Brand di-resolve saat query dijalankan, bukan saat model di-boot, supaya
 * paket core tetap aman dipakai tanpa aplikasi Laravel (config() bisa absen).
 */
final class BrandScope implements Scope
{
    /**
     * Sentinel brand yang tidak mungkin cocok dengan baris mana pun.
     *
     * Kalau `app.brand` tidak ter-resolve (mis. app tanpa konfigurasi), scope
     * harus GAGAL TERTUTUP (nol baris), bukan diam-diam membocorkan data brand
     * lain dengan default ke brand nyata.
     */
    private const UNSET = '__brand_unset__';

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.brand', self::brand());
    }

    public static function brand(): string
    {
        try {
            $brand = function_exists('config') ? config('app.brand') : null;
        } catch (\Throwable) {
            $brand = null;
        }

        return $brand ?: self::UNSET;
    }
}
