<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBookmaker extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'users_bookmakers';

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'users_bankrolls_id',
        'bookmakers_id',
        'bookmaker_start_amount',
        'bookmaker_actual_amount',
        'bookmaker_comment'
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'bookmaker_start_amount' => 'decimal:2',
        'bookmaker_actual_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtenir l'utilisateur associé à cette relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtenir le bookmaker associé à cette relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bookmaker(): BelongsTo
    {
        return $this->belongsTo(Bookmaker::class, 'bookmakers_id');
    }

    /**
     * Obtenir la bankroll associée à cette relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bankroll(): BelongsTo
    {
        return $this->belongsTo(UserBankroll::class, 'users_bankrolls_id');
    }
}