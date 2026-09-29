<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = ['last_seen_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /** Nomor HP disamarkan: 4 digit awal + •••• + 4 digit akhir. */
    public function maskedPhone(): string
    {
        $phone = (string) $this->phone;

        return strlen($phone) <= 8 ? $phone : substr($phone, 0, 4).'••••'.substr($phone, -4);
    }
}
