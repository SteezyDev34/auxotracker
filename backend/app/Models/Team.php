<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nickname',
        'slug',
        'img',
        'sofascore_id',
        'league_id',
        'gender',
        'country_code',
        'date_of_birth',
        'height',
        'weight',
        'playing_hand',
        'backhand',
        'birthplace',
        'residence',
        'coach'
    ];

    // Relations
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'team1_id');
    }

    public function awayEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'team2_id');
    }

    public function events()
    {
        return $this->homeEvents->merge($this->awayEvents);
    }

    /**
     * Trouve l'ID d'une équipe par son nom ou nickname (LIKE), utile pour l'import depuis des strings.
     */
    public static function findIdByName(?string $name): ?int
    {
        if (!$name) {
            return null;
        }

        $escaped = self::escapeLike($name);

        $team = self::where('name', 'like', '%' . $escaped . '%')
            ->orWhere('nickname', 'like', '%' . $escaped . '%')
            ->first();

        return $team ? $team->id : null;
    }

    /**
     * Escape special characters for SQL LIKE queries.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Trouver l'ID d'une équipe en acceptant soit le sofascore_id soit un nom.
     * Si la valeur est numérique, on tente une recherche par `sofascore_id`, sinon on utilise la recherche par nom.
     */
    public static function findIdBySofascoreOrName(?string $value): ?int
    {
        if (!$value) {
            return null;
        }

        // Si la valeur est un entier (ou une string numérique), rechercher par sofascore_id
        if (ctype_digit((string) $value)) {
            $team = self::where('sofascore_id', intval($value))->first();
            if ($team) {
                return $team->id;
            }
        }

        // Sinon, rechercher par nom/nickname (LIKE)
        return self::findIdByName($value);
    }
}