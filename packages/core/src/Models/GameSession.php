<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    protected $guarded = [];

    protected $casts = ['tier_config' => 'array', 'expires_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Semua kado sesi ini, dari semua kesempatan. */
    public function prizes(): HasMany
    {
        return $this->hasMany(GamePrize::class);
    }

    /** Kesempatan yang sedang dimainkan (1-based). Setelah kesempatan habis tetap di kesempatan terakhir. */
    public function currentRound(): int
    {
        return max(1, min($this->chance_used + 1, $this->chance_total));
    }

    /** 24 kado di papan kesempatan yang sedang dimainkan. */
    public function boardPrizes(): HasMany
    {
        return $this->prizes()->where('round', $this->currentRound());
    }
}
