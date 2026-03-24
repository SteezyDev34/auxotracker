<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'country_id',
        'sport_id',
        'slug',
        'img',
        'sofascore_id',
        'priority'
        'sofascore_id',
        'priority'
    ];

    // Relations
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}


    /**
     * Trouver l'ID d'une ligue en acceptant soit le sofascore_id soit un nom.
     */
    public static function findIdBySofascoreOrName(?string $value): ?int
    {
        if (!$value) {
            return null;
        }

        if (ctype_digit((string) $value)) {
            $league = self::where('sofascore_id', intval($value))->first();
            if ($league) {
                return $league->id;
            }
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        $league = self::where('name', 'like', '%' . $escaped . '%')->first();

        return $league ? $league->id : null;
    }
}
