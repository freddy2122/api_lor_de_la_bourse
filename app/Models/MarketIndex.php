<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketIndex extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'last_value', 'change_abs', 'change_pct', 'market_timestamp',
    ];

    protected $casts = [
        'market_timestamp' => 'datetime',
    ];
}
