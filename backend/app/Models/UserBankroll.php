<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserBankroll extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'users_bankrolls';

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bankroll_name',
        'bankroll_start_amount',
        'bankroll_actual_amount',
        'bankroll_description'
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'bankroll_start_amount' => 'decimal:2',
        'bankroll_actual_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtenir l'utilisateur associé à cette bankroll.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtenir les associations utilisateur-bookmaker pour cette bankroll.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userBookmakers(): HasMany
    {
        return $this->hasMany(UserBookmaker::class, 'users_bankrolls_id');
    }
}