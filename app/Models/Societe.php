<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Societe extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'sector',
        'country',
        'slug',
        'brvm_url',
        'headquarters',
        'market_cap_fcfa',
        'dividend_yield_pct',
        'description',
        'extra',
    ];

    protected $casts = [
        'market_cap_fcfa' => 'float',
        'dividend_yield_pct' => 'float',
        'extra' => 'array',
    ];
}
