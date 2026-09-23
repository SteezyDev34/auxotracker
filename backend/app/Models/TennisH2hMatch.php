<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisH2hMatch extends Model
{
    protected $table = 'tennis_h2h_matches';

    protected $fillable = [
        'current_event_id',
        'h2h_event_id',
        'tournament_name',
        'ground_type',
        'played_at',
        'home_team_sofascore_id',
        'away_team_sofascore_id',
        'home_score',
        'away_score',
        'winner_code',
        'fetched_at',
    ];

    protected $casts = [
        'home_score' => 'array',
        'away_score' => 'array',
        'played_at' => 'date',
        'fetched_at' => 'datetime',
    ];
}
