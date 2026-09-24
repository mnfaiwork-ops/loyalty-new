<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Claim extends Model
{
    protected $guarded = [];
    protected $casts = ['claimed_at' => 'datetime', 'expires_at' => 'datetime'];

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function gamePrize(): BelongsTo
    {
        return $this->belongsTo(GamePrize::class, 'game_prize_id');
    }
}
