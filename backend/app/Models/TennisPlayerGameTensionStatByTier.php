<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisPlayerGameTensionStatByTier extends Model
{
    protected $table = 'tennis_player_game_tension_stats_by_tier';

    protected $fillable = [
        'team_id',
        'opponent_tier',
        'sample_matches',
        'sample_games_set1',
        'count_reach_15a',
        'count_reach_30a',
        'count_reach_40a',
        'count_reach_30love',
        'count_matches_reach_15a',
        'count_matches_reach_30a',
        'count_matches_reach_40a',
        'count_matches_reach_30love',
        'count_matches_reach_g40_0',
        'count_matches_reach_g40_15',
        'count_matches_reach_g40_30',
        'count_matches_led_15_0',
        'count_matches_lost_first_point_on_serve',
        'count_game_40_0',
        'count_game_40_15',
        'count_game_40_30',
        'sample_service_games_set1',
        'count_server_loss_to_love',
        'count_led_15_0',
        'count_lost_first_point_on_serve',
        'updated_from_cache_at',
    ];

    protected $casts = [
        'updated_from_cache_at' => 'datetime',
    ];

    /**
     * Tranches de classement ATP/WTA (singles ranking) utilisées pour
     * regrouper les adversaires par "style"/niveau. Un classement absent
     * (joueur non classé / donnée manquante) tombe dans "inconnu".
     */
    public static function tierForRanking(?int $ranking): string
    {
        if ($ranking === null) {
            return 'inconnu';
        }
        if ($ranking <= 50) {
            return 'top50';
        }
        if ($ranking <= 150) {
            return '50-150';
        }
        return '150+';
    }

    public function rate15a(): ?float
    {
        return $this->sample_games_set1 ? $this->count_reach_15a / $this->sample_games_set1 : null;
    }

    public function rate30a(): ?float
    {
        return $this->sample_games_set1 ? $this->count_reach_30a / $this->sample_games_set1 : null;
    }

    public function rate40a(): ?float
    {
        return $this->sample_games_set1 ? $this->count_reach_40a / $this->sample_games_set1 : null;
    }

    public function rate30Love(): ?float
    {
        return $this->sample_games_set1 ? $this->count_reach_30love / $this->sample_games_set1 : null;
    }

    public function rateMatchReach15a(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_15a / $this->sample_matches : null;
    }

    public function rateMatchReach30a(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_30a / $this->sample_matches : null;
    }

    public function rateMatchReach40a(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_40a / $this->sample_matches : null;
    }

    public function rateMatchReach30Love(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_30love / $this->sample_matches : null;
    }

    public function rateMatchReachG40_0(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_g40_0 / $this->sample_matches : null;
    }

    public function rateMatchReachG40_15(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_g40_15 / $this->sample_matches : null;
    }

    public function rateMatchReachG40_30(): ?float
    {
        return $this->sample_matches ? $this->count_matches_reach_g40_30 / $this->sample_matches : null;
    }

    public function rateMatchLed15_0(): ?float
    {
        return $this->sample_matches ? $this->count_matches_led_15_0 / $this->sample_matches : null;
    }

    public function rateMatchLostFirstPointOnServe(): ?float
    {
        return $this->sample_matches ? $this->count_matches_lost_first_point_on_serve / $this->sample_matches : null;
    }

    public function rateGame40_0(): ?float
    {
        return $this->sample_games_set1 ? $this->count_game_40_0 / $this->sample_games_set1 : null;
    }

    public function rateGame40_15(): ?float
    {
        return $this->sample_games_set1 ? $this->count_game_40_15 / $this->sample_games_set1 : null;
    }

    public function rateGame40_30(): ?float
    {
        return $this->sample_games_set1 ? $this->count_game_40_30 / $this->sample_games_set1 : null;
    }

    public function rateServerLossToLove(): ?float
    {
        return $this->sample_service_games_set1 ? $this->count_server_loss_to_love / $this->sample_service_games_set1 : null;
    }
}
