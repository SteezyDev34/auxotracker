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
        'sofascore_id'
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