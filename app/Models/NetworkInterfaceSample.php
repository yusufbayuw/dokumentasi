<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkInterfaceSample extends Model
{
    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
        'rx_mbps' => 'decimal:3',
        'tx_mbps' => 'decimal:3',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function deviceInterface(): BelongsTo
    {
        return $this->belongsTo(DeviceInterface::class);
    }
}
