<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $guarded = [];
    protected $casts = ['claimed_at' => 'datetime', 'expires_at' => 'datetime'];
}
