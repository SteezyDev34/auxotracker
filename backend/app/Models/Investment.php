<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'investments';

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bankroll_id',
        'montant_investi',
        'date_investissement',
        'statut',
        'commentaire'
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_investi' => 'decimal:2',
        'date_investissement' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtenir l'utilisateur associé à cet investissement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir les intérêts liés à cet investissement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function interets(): HasMany
    {
        return $this->hasMany(Interet::class, 'user_id', 'user_id')
            ->where('bankroll_id', $this->bankroll_id);
    }

    /**
     * Scope pour filtrer par utilisateur.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par bankroll.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $bankrollId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByBankroll($query, $bankrollId)
    {
        return $query->where('bankroll_id', $bankrollId);
    }

    /**
     * Scope pour les investissements actifs.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Calculer le montant total investi par un utilisateur dans une bankroll.
     *
     * @param int $userId
     * @param int|null $bankrollId
     * @return float
     */
    public static function getTotalInvestedAmount($userId, $bankrollId = null)
    {
        $query = self::where('user_id', $userId)
            ->where('statut', 'actif');

        if ($bankrollId !== null) {
            $query->where('bankroll_id', $bankrollId);
        }

        return $query->sum('montant_investi');
    }

    /**
     * Calculer le montant total investi par un utilisateur dans une bankroll à une date donnée.
     *
     * @param int $userId
     * @param string $date
     * @param int|null $bankrollId
     * @return float
     */
    public static function getTotalInvestedAmountAtDate($userId, $date, $bankrollId = null)
    {
        $query = self::where('user_id', $userId)
            ->where('date_investissement', '<=', $date)
            ->where('statut', 'actif');

        if ($bankrollId !== null) {
            $query->where('bankroll_id', $bankrollId);
        }

        return $query->sum('montant_investi');
    }
}
