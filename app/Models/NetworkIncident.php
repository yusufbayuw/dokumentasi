<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkIncident extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(NetworkQualityTarget::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NetworkIncidentEvent::class);
    }
}
