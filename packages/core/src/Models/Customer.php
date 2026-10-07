<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'pin_version' => 'int',
        'pin_must_change' => 'bool',
        'pin_set_at' => 'datetime',
        'pin_reset_at' => 'datetime',
    ];

    protected $hidden = ['pin_hash'];

    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    /** Staf CS yang terakhir membuat PIN sementara. */
    public function pinResetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pin_reset_by');
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
