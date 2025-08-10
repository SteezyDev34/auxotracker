<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'team1_id',
        'team2_id',
        'league_id',
        'type',
        'market',
        'odd',
        'event_date'
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'odd' => 'decimal:2'
    ];

    // Relations
    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function bets(): BelongsToMany
    {
        return $this->belongsToMany(Bet::class, 'bet_event');
    }

    // Méthodes utilitaires
    public function getEventNameAttribute()
    {
        $team1Name = $this->team1 ? $this->team1->name : 'Équipe 1';
        $team2Name = $this->team2 ? $this->team2->name : 'Équipe 2';
        return "{$team1Name} vs {$team2Name}";
    }

    public function getLeagueNameAttribute()
    {
        return $this->league ? $this->league->name : 'Ligue inconnue';
    }

    public function getFormattedEventDateAttribute()
    {
        return $this->event_date ? $this->event_date->format('d/m/Y H:i') : '';
    }
} 