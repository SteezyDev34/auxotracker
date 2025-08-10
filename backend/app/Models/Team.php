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
        'league_id'
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
}