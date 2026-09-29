<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat poin per lot: earn, spend (negatif), refund, expire (negatif). */
class PointMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['points' => 'int', 'created_at' => 'datetime'];
}
