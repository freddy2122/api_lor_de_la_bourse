<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'instrument_id', 'last_price', 'open', 'high', 'low', 'previous_close',
        'change_abs', 'change_pct', 'volume', 'value_traded', 'market_timestamp',
    ];

    protected $casts = [
        'market_timestamp' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
