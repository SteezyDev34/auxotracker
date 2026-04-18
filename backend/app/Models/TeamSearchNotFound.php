<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSearchNotFound extends Model
{
    use HasFactory;

    protected $table = 'team_search_not_found';

    protected $fillable = [
        'search_term',
        'user_id',
        'sport_id',
        'resolved',
        'team_id',
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
