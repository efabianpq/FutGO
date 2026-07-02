<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;

class SupportServiceStatus extends Model
{
    protected $table = 'support_service_status';

    protected $fillable = ['component', 'status', 'message', 'last_checked_at', 'auto_detected'];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'auto_detected'   => 'boolean',
    ];

    public function scopeByComponent($q, string $component)
    {
        return $q->where('component', $component);
    }

    public function isOperational(): bool
    {
        return $this->status === 'operativo';
    }

    public function hasProblem(): bool
    {
        return in_array($this->status, ['caido', 'degradado']);
    }
}
