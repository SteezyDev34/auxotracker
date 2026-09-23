<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'type', 'sport', 'subject_type', 'subject_id', 'subject_name',
        'subject_img', 'description', 'metadata', 'level', 'command',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public static function log(string $type, string $description, array $options = []): self
    {
        return self::create([
            'type'         => $type,
            'sport'        => $options['sport']        ?? null,
            'subject_type' => $options['subject_type'] ?? null,
            'subject_id'   => $options['subject_id']   ?? null,
            'subject_name' => $options['subject_name'] ?? null,
            'subject_img'  => $options['subject_img']  ?? null,
            'description'  => $description,
            'metadata'     => $options['metadata']     ?? null,
            'level'        => $options['level']        ?? 'info',
            'command'      => $options['command']      ?? null,
        ]);
    }
}
