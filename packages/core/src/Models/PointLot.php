<?php

namespace LoyaltyNew\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu kelompok poin dengan tanggal hangus sendiri (satu kado poin = satu lot). */
class PointLot extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'points' => 'int', 'remaining' => 'int'];
}
