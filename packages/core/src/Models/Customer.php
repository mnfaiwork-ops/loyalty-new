<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
