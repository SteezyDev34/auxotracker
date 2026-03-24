<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interet extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'interets';

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bankroll_id',
        'montant_total_investi_date_versement',
        'montant_interet',
        'taux_interet',
        'moyen_paiement',
        'detail_paiement',
        'date_versement',
        'commentaire'
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant_total_investi_date_versement' => 'decimal:2',
        'montant_interet' => 'decimal:2',
        'taux_interet' => 'decimal:2',
        'date_versement' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtenir l'utilisateur associé à cet intérêt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour filtrer par période.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPeriod($query, $period)
    {
        $now = now();

        switch ($period) {
            case '3m':
                $query->where('date_versement', '>=', $now->subMonths(3));
                break;
            case '6m':
                $query->where('date_versement', '>=', $now->subMonths(6));
                break;
            case '1an':
                $query->where('date_versement', '>=', $now->subYear());
                break;
            case 'all':
                // Pas de filtre de date
                break;
            default:
                // Pas de filtre de date
                break;
        }

        return $query;
    }

    /**
     * Scope pour filtrer par moyen de paiement.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $moyenPaiement
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMoyenPaiement($query, $moyenPaiement)
    {
        return $query->where('moyen_paiement', $moyenPaiement);
    }

    /**
     * Formater le détail de paiement pour l'affichage.
     *
     * @return string
     */
    public function getDetailPaiementFormateAttribute()
    {
        if (!$this->detail_paiement) {
            return 'Non spécifié';
        }

        switch ($this->moyen_paiement) {
            case 'paypal':
                return $this->detail_paiement; // Email PayPal affiché tel quel
            case 'virement_bancaire':
                // Masquer l'IBAN en gardant les 4 premiers et 4 derniers caractères
                $iban = $this->detail_paiement;
                if (strlen($iban) > 8) {
                    $debut = substr($iban, 0, 4);
                    $fin = substr($iban, -4);
                    $milieu = str_repeat('*', strlen($iban) - 8);
                    return $debut . ' ' . $milieu . ' ' . $fin;
                }
                return $iban;
            default:
                return $this->detail_paiement;
        }
    }

    /**
     * Obtenir le libellé du moyen de paiement.
     *
     * @return string
     */
    public function getMoyenPaiementLibelleAttribute()
    {
        return match ($this->moyen_paiement) {
            'paypal' => 'PayPal',
            'virement_bancaire' => 'Virement bancaire',
            'autre' => 'Autre',
            default => 'Non spécifié'
        };
    }

    /**
     * Obtenir la relation avec les investissements de l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function investments()
    {
        return $this->hasMany(Investment::class, 'user_id', 'user_id')
            ->where('bankroll_id', $this->bankroll_id);
    }

    /**
     * Calculer le taux d'intérêt effectif.
     *
     * @return float
     */
    public function getTauxInteretEffectifAttribute()
    {
        if ($this->montant_total_investi_date_versement > 0) {
            return ($this->montant_interet / $this->montant_total_investi_date_versement) * 100;
        }
        return 0;
    }
}
