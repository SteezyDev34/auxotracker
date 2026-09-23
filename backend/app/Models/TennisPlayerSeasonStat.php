<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TennisPlayerSeasonStat extends Model
{
    protected $table = 'tennis_player_season_stats';

    protected $fillable = [
        'team_id',
        'ground_type',
        'season_year',
        'aces',
        'double_faults',
        'first_serve_points_scored',
        'first_serve_points_total',
        'second_serve_points_scored',
        'second_serve_points_total',
        'break_points_scored',
        'break_points_total',
        'opponent_break_points_scored',
        'opponent_break_points_total',
        'tiebreaks_won',
        'tiebreaks_losses',
        'wins',
        'matches_count',
        'tournaments_played',
        'tournaments_won',
        'winners_total',
        'unforced_errors_total',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function firstServePct(): ?float
    {
        if (!$this->first_serve_points_total) {
            return null;
        }
        return round(100 * $this->first_serve_points_scored / $this->first_serve_points_total, 1);
    }

    public function servicePointsWonPct(): ?float
    {
        $total = ($this->first_serve_points_total ?? 0) + ($this->second_serve_points_total ?? 0);
        if (!$total) {
            return null;
        }
        $scored = ($this->first_serve_points_scored ?? 0) + ($this->second_serve_points_scored ?? 0);
        return round(100 * $scored / $total, 1);
    }

    public function breakPointConversionPct(): ?float
    {
        if (!$this->break_points_total) {
            return null;
        }
        return round(100 * $this->break_points_scored / $this->break_points_total, 1);
    }

    public function breakPointsSavedPct(): ?float
    {
        if (!$this->opponent_break_points_total) {
            return null;
        }
        $saved = $this->opponent_break_points_total - $this->opponent_break_points_scored;
        return round(100 * $saved / $this->opponent_break_points_total, 1);
    }

    public function tiebreakRatePerMatch(): ?float
    {
        if (!$this->matches_count) {
            return null;
        }
        return round((($this->tiebreaks_won ?? 0) + ($this->tiebreaks_losses ?? 0)) / $this->matches_count, 2);
    }
}
