<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardCatalogItem extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'bool', 'points_cost' => 'int', 'stock' => 'int', 'stock_used' => 'int'];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(RewardCatalog::class, 'reward_catalog_id');
    }

    /** Stok kosong berarti tanpa batas. */
    public function inStock(): bool
    {
        return $this->stock === null || $this->stock_used < $this->stock;
    }

    public function remaining(): ?int
    {
        return $this->stock === null ? null : max(0, $this->stock - $this->stock_used);
    }
}
