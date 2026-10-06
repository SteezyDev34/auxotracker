<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisMatchTightnessScore extends Model
{
    protected $table = 'tennis_match_tightness_scores';

    protected $fillable = [
        'event_id',
        'score',
        'breakdown',
        'prob_15a_set1',
        'prob_30a_set1',
        'prob_40a_set1',
        'edge_15a',
        'edge_30a',
        'edge_40a',
        'sample_size',
        'prob_30love_set1',
        'prob_15a_in_set',
        'prob_30a_in_set',
        'prob_40a_in_set',
        'prob_30love_in_set',
        'prob_game_40_0',
        'prob_game_40_15',
        'prob_game_40_30',
        'prob_game_40_0_in_set',
        'prob_game_40_15_in_set',
        'prob_game_40_30_in_set',
        'prob_server_loss_to_love',
        'prob_team1_leads_15_0',
        'prob_team2_leads_15_0',
        'prob_team1_leads_15_0_in_set',
        'prob_team2_leads_15_0_in_set',
        'edge_30love',
        'edge_g40_0',
        'edge_g40_15',
        'edge_g40_30',
        'edge_leads1',
        'edge_leads2',
        'prob_lost_serve1',
        'prob_lost_serve2',
        'prob_lost_serve1_in_set',
        'prob_lost_serve2_in_set',
        'edge_lost_serve1',
        'edge_lost_serve2',
        'computed_at',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'computed_at' => 'datetime',
    ];
}
