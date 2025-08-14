<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bookmaker extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bookmaker_name',
        'bookmaker_img'
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtenir les associations utilisateur-bookmaker pour ce bookmaker.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userBookmakers(): HasMany
    {
        return $this->hasMany(UserBookmaker::class, 'bookmakers_id');
    }
}