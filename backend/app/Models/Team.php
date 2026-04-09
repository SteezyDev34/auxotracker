<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Http;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nickname',
        'short_name',
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

    /**
     * Many-to-many relation to allow a team to belong to multiple leagues.
     * Keep `league_id` for primary league compatibility.
     */
    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_team', 'team_id', 'league_id')->withTimestamps();
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
    public static function findIdBySofascoreOrName(?string $value, $sportId = null): ?int
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

        // Sinon, si un sportId est fourni, interroger l'API Auxotracker pour une correspondance distante
        if ($sportId) {
            try {
                $base = env('AUXOTRACKER_API_URL', 'https://api.auxotracker.p-com.studio');
                $path = rtrim($base, '/') . '/api/sports/' . intval($sportId) . '/teams/search';
                $resp = Http::timeout(3)->get($path, ['search' => $value]);

                if ($resp->successful()) {
                    $json = $resp->json();

                    // Extraire une liste possible de résultats (s'adapte à plusieurs formats)
                    $list = [];
                    if (is_array($json)) {
                        if (isset($json['data']) && is_array($json['data'])) {
                            $list = $json['data'];
                        } elseif (isset($json['teams']) && is_array($json['teams'])) {
                            $list = $json['teams'];
                        } else {
                            $list = $json;
                        }
                    }

                    if (!empty($list)) {
                        $first = $list[0];

                        // Chercher un sofascore_id connu dans la réponse
                        $possibleIds = [
                            $first['sofascore_id'] ?? null,
                            $first['sofa_id'] ?? null,
                            $first['external_id'] ?? null,
                            $first['id'] ?? null
                        ];

                        foreach ($possibleIds as $pid) {
                            if ($pid && ctype_digit((string) $pid)) {
                                $team = self::where('sofascore_id', intval($pid))->first();
                                if ($team) {
                                    return $team->id;
                                }
                            }
                        }

                        // Essayer une correspondance par nom exacte puis LIKE
                        $name = $first['name'] ?? $first['team_name'] ?? null;
                        if ($name) {
                            $team = self::where('name', $name)->orWhere('nickname', $name)->first();
                            if ($team) {
                                return $team->id;
                            }

                            $escaped = self::escapeLike($name);
                            $team = self::where('name', 'like', '%' . $escaped . '%')
                                ->orWhere('nickname', 'like', '%' . $escaped . '%')
                                ->first();
                            if ($team) {
                                return $team->id;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ne pas planter si l'API est indisponible; on retombe sur la recherche locale
            }
        }

        // Fallback: rechercher par nom/nickname (LIKE)
        return self::findIdByName($value);
    }
}
