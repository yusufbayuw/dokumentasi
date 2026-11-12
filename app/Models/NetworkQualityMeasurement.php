<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkQualityMeasurement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
        'latency_ms' => 'decimal:2',
        'packet_loss_pct' => 'decimal:2',
        'rtt_variation_ms' => 'decimal:2',
        'samples_sent' => 'integer',
        'samples_received' => 'integer',
        'duration_ms' => 'integer',
        'response_code' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(NetworkQualityTarget::class);
    }
    public function probeLocation(): BelongsTo
    {
        return $this->belongsTo(NetworkProbeLocation::class);
    }
}
