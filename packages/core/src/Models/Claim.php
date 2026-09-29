<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'redeem_requested_at' => 'datetime',
        'points_spent' => 'int',
    ];

    protected static function booted(): void
    {
        // Klaim Gatcha yang dibuat tanpa brand/pelanggan mengambilnya dari sesi.
        static::creating(function (Claim $claim) {
            if ($claim->game_session_id && (! $claim->brand || ! $claim->customer_id)) {
                $session = GameSession::withoutGlobalScopes()->find($claim->game_session_id);
                $claim->brand = $claim->brand ?: $session?->brand;
                $claim->customer_id = $claim->customer_id ?: $session?->customer_id;
            }
        });
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function gamePrize(): BelongsTo
    {
        return $this->belongsTo(GamePrize::class, 'game_prize_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(RewardCatalogItem::class, 'reward_catalog_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClaimEvent::class)->orderBy('id');
    }

    public function isBigPrize(): bool
    {
        return $this->gamePrize?->tier === 'hadiah_besar';
    }

    /** Sisa hari sebelum batas redeem, dibulatkan ke atas, minimal 0. */
    public function daysLeft(): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->expires_at, false) / 86400));
    }
}
