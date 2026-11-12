<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceInterface extends Model
{
    use HasFactory;

    protected $casts = [
        'is_management' => 'boolean',
        'snmp_if_index' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function ipAddress(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class, 'ip_address_id');
    }
    public function qualitySamples(): HasMany
    {
        return $this->hasMany(NetworkInterfaceSample::class);
    }
}
