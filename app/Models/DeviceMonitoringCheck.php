<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMonitoringCheck extends Model
{
    use HasFactory;

    protected $casts = [
        'checked_at' => 'datetime',
        'latency_ms' => 'decimal:2',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }
}
