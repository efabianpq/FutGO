<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportIncidentPattern extends Model
{
    protected $fillable = [
        'pattern_key', 'tickets_count', 'first_detected_at', 'team_alerted_at', 'resolved',
    ];

    protected $casts = [
        'first_detected_at' => 'datetime',
        'team_alerted_at'   => 'datetime',
        'resolved'          => 'boolean',
    ];
}
