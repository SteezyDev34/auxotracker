<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'team_1_sofascore_id',
        'team_2_sofascore_id',
        'match_start_date',
        'match_start_time',
        'tournament_sofascore_id',
        'sport_id',
        'sofascore_link'
    ];
}
