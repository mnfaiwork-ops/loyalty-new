<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeCatalog extends Model
{
    protected $table = 'prize_catalog';
    protected $guarded = [];
    protected $casts = ['is_capped' => 'bool', 'active' => 'bool'];
}
