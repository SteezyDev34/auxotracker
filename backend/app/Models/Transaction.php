<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;

    /**
     * Les attributs qui peuvent être assignés en masse.
     */
    protected $fillable = [
        'type',
        'amount',
        'transaction_date',
        'description',
        'method'
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2'
    ];

    /**
     * Scope pour filtrer par période
     */
    public function scopeByPeriod(Builder $query, string $period): Builder
    {
        $now = Carbon::now();
        
        return match($period) {
            '7j' => $query->where('transaction_date', '>=', $now->subDays(7)),
            '30j' => $query->where('transaction_date', '>=', $now->subDays(30)),
            '90j' => $query->where('transaction_date', '>=', $now->subDays(90)),
            '1an' => $query->where('transaction_date', '>=', $now->subYear()),
            'tout' => $query,
            default => $query->where('transaction_date', '>=', $now->subDays(30))
        };
    }

    /**
     * Scope pour filtrer par type de transaction
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les dépôts uniquement
     */
    public function scopeDeposits(Builder $query): Builder
    {
        return $query->where('type', 'deposit');
    }

    /**
     * Scope pour les retraits uniquement
     */
    public function scopeWithdrawals(Builder $query): Builder
    {
        return $query->where('type', 'withdraw');
    }
}
