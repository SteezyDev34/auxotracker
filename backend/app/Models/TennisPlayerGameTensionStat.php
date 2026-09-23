<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisPlayerGameTensionStat extends Model
{
    protected $table = 'tennis_player_game_tension_stats';

    protected $fillable = [
        'team_id',
        'sample_matches',
        'sample_games_set1',
        'count_reach_15a',
        'count_reach_30a',
        'count_reach_40a',
        'count_reach_30love',
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

    /** Seuil minimum de jeux échantillonnés pour considérer la fréquence empirique fiable. */
    public const MIN_SAMPLE_GAMES = 15;

    public function isReliable(): bool
    {
        return $this->sample_games_set1 >= self::MIN_SAMPLE_GAMES;
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

    /** Dénominateur spécifique : jeux servis par CE joueur (pas tous les jeux). */
    public function rateServerLossToLove(): ?float
    {
        return $this->sample_service_games_set1 ? $this->count_server_loss_to_love / $this->sample_service_games_set1 : null;
    }

    /** Proba que CE joueur (spécifiquement) gagne le tout premier point du jeu (mène 15-0). */
    public function rateLedFirstPoint(): ?float
    {
        return $this->sample_games_set1 ? $this->count_led_15_0 / $this->sample_games_set1 : null;
    }

    /** Proba que CE joueur perde le 1er point QUAND IL SERT (dénominateur = ses jeux de service uniquement). */
    public function rateLostFirstPointOnServe(): ?float
    {
        return $this->sample_service_games_set1 ? $this->count_lost_first_point_on_serve / $this->sample_service_games_set1 : null;
    }
}
