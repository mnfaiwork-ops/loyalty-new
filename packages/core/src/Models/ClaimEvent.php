<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu perpindahan status klaim: jejak audit sekaligus timeline di layar pelanggan. */
class ClaimEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
