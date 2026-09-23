<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisMatchOdds extends Model
{
    protected $table = 'tennis_match_odds';

    protected $fillable = [
        'event_id',
        'market_id',
        'market_name',
        'choice_name',
        'fractional_value',
        'decimal_value',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
