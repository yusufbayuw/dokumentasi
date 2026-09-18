<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_device_id',
        'source_interface_id',
        'target_device_id',
        'target_interface_id',
        'source',
        'connection_type',
        'source_port',
        'target_port',
        'speed',
        'vlan',
        'is_primary',
        'status',
        'label',
        'description',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }

    public function sourceInterface(): BelongsTo
    {
        return $this->belongsTo(DeviceInterface::class, 'source_interface_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    public function targetInterface(): BelongsTo
    {
        return $this->belongsTo(DeviceInterface::class, 'target_interface_id');
    }
}
