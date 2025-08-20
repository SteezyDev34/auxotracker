<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Tipster;
use App\Models\UserSportPreference;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'username',
        'email',
        'password',
        'email_verified_at',
        'user_lastname',
        'user_firstname',
        'user_profile_picture',
        'user_banner_picture',
        'user_level',
        'user_birthdate',
        'user_language',
        'user_currency',
        'user_sort_bets_by',
        'user_welcome_page',
        'user_timezone',
        'user_display_dashboard',
        'user_duplicate_bet_date',
        'user_bookmaker_list',
        'user_sport_list',
        'role',
        'remember_token'
    ];

    /**
     * Les attributs qui doivent être masqués pour la sérialisation.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs qui doivent être convertis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'user_birthdate' => 'date',
        'user_bookmaker_list' => 'array',
        'user_sport_list' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Retourne l'URL de l'avatar de l'utilisateur.
     *
     * @return string
     */
    public function getAvatarUrl()
    {
        return $this->user_profile_picture
            ? asset('storage/avatar/' . $this->user_profile_picture)
            : asset('storage/avatar/user.jpg');
    }

    /**
     * Obtenir les associations utilisateur-bookmaker pour cet utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userBookmakers(): HasMany
    {
        return $this->hasMany(UserBookmaker::class, 'user_id');
    }

    /**
     * Obtenir les bookmakers associés à cet utilisateur via la table pivot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function bookmakers()
    {
        return $this->belongsToMany(Bookmaker::class, 'users_bookmakers', 'user_id', 'bookmakers_id')
            ->withPivot(['bookmaker_start_amount', 'bookmaker_actual_amount', 'bookmaker_comment'])
            ->withTimestamps();
    }

    /**
     * Obtenir les bankrolls de l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bankrolls(): HasMany
    {
        return $this->hasMany(UserBankroll::class, 'user_id');
    }

    /**
     * Obtenir les tipsters de l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tipsters(): HasMany
    {
        return $this->hasMany(Tipster::class, 'user_id');
    }

    /**
     * Obtenir les préférences sportives de l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function sportsPreferences(): HasMany
    {
        return $this->hasMany(UserSportPreference::class, 'user_id');
    }

    /**
     * Obtenir les sports favoris de l'utilisateur ordonnés.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function favoriteSports(): HasMany
    {
        return $this->hasMany(UserSportPreference::class, 'user_id')
            ->favorites()
            ->ordered()
            ->with('sport');
    }
}
