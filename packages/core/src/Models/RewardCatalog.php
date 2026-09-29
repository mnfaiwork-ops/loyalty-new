<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Katalog tukar poin satu bulan (period = YYYY-MM). */
class RewardCatalog extends Model
{
    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RewardCatalogItem::class)->orderBy('sort')->orderBy('id');
    }
}
