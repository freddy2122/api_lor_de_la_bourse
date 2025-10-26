<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticker', 'name', 'sector', 'isin', 'currency', 'status',
    ];

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany('market_timestamp');
    }
}
