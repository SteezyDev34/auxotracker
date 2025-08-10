<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bet extends Model
{
    use HasFactory;

    protected $fillable = [
        'bet_date',
        'global_odds',
        'bet_code',
        'result',
        'sport_id',
        'stake'
    ];

    protected $casts = [
        'stake' => 'decimal:2',
        'global_odds' => 'decimal:2',
        'bet_date' => 'datetime'
    ];

    // Relations
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'bet_event');
    }

    // Scopes pour les filtres
    public function scopeBySport($query, $sports)
    {
        if (!empty($sports)) {
            $query->whereHas('sport', function($q) use ($sports) {
                $q->whereIn('name', $sports);
            });
        }
        return $query;
    }

    public function scopeByBetType($query, $betTypes)
    {
        // Pour l'instant, on utilise le bet_code comme type de pari
        if (!empty($betTypes)) {
            $query->whereIn('bet_code', $betTypes);
        }
        return $query;
    }

    public function scopeByBookmaker($query, $bookmakers)
    {
        // Pour l'instant, on utilise le bet_code comme bookmaker
        if (!empty($bookmakers)) {
            $query->whereIn('bet_code', $bookmakers);
        }
        return $query;
    }

    public function scopeByTipster($query, $tipsters)
    {
        // Pour l'instant, on utilise le bet_code comme tipster
        if (!empty($tipsters)) {
            $query->whereIn('bet_code', $tipsters);
        }
        return $query;
    }

    public function scopeByPeriod($query, $period)
    {
        $now = now();
        
        switch ($period) {
            case '7j':
                $query->where('bet_date', '>=', $now->subDays(7));
                break;
            case '30j':
                $query->where('bet_date', '>=', $now->subDays(30));
                break;
            case '3m':
                $query->where('bet_date', '>=', $now->subMonths(3));
                break;
            case '6m':
                $query->where('bet_date', '>=', $now->subMonths(6));
                break;
            case '1an':
                $query->where('bet_date', '>=', $now->subYear());
                break;
            case 'all':
                // Pas de filtre de date
                break;
            default:
                $query->where('bet_date', '>=', $now->subDays(30));
        }
        
        return $query;
    }

    // Méthodes utilitaires
    public function getStatusColorAttribute()
    {
        return match($this->result) {
            'won' => 'success',
            'lost' => 'danger',
            'void' => 'secondary',
            'pending' => 'warning',
            default => 'info'
        };
    }

    public function getProfitLossFormattedAttribute()
    {
        $profitLoss = $this->calculateProfitLoss();
        return number_format($profitLoss, 2) . ' €';
    }

    public function getStakeFormattedAttribute()
    {
        return number_format($this->stake, 2) . ' €';
    }

    public function getPotentialWinFormattedAttribute()
    {
        $potentialWin = $this->stake * $this->global_odds;
        return number_format($potentialWin, 2) . ' €';
    }

    public function calculateProfitLoss()
    {
        if ($this->result === 'won') {
            return ($this->stake * $this->global_odds) - $this->stake;
        } elseif ($this->result === 'lost') {
            return -$this->stake;
        } elseif ($this->result === 'void') {
            return 0;
        }
        return 0; // pending
    }

    public function getSportNameAttribute()
    {
        return $this->sport ? $this->sport->name : 'Sport inconnu';
    }
} 