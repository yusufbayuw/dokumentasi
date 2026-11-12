<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkSlaDaily extends Model
{
    protected $table = 'network_sla_daily';

    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'observed_availability_pct' => 'decimal:2',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(NetworkQualityTarget::class);
    }
}
