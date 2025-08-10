<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'img'
    ];

    // Relations
    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }
}