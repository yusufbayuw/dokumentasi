<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    use HasFactory;

    protected $casts = [
        'location' => 'json',
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'monitoring_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_latency_ms' => 'decimal:2',
        'consecutive_failures' => 'integer',
        'auto_reboot_enabled' => 'boolean',
        'last_auto_reboot_at' => 'datetime',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id', 'id');
    }

    public function ip(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class, 'ip_address_id', 'id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeDevice::class, 'type_device_id', 'id');
    }

    public function ownerUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'owner_unit_id', 'id');
    }

    public function topology(): HasOne
    {
        return $this->hasOne(Topology::class, 'device_id', 'id');
    }

    public function monitoringChecks(): HasMany
    {
        return $this->hasMany(DeviceMonitoringCheck::class, 'device_id', 'id');
    }

    public function latestMonitoringCheck(): HasOne
    {
        return $this->hasOne(DeviceMonitoringCheck::class, 'device_id', 'id')->latestOfMany('checked_at');
    }

    public function interfaces(): HasMany
    {
        return $this->hasMany(DeviceInterface::class, 'device_id', 'id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DeviceCredential::class, 'device_id', 'id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(DeviceMaintenance::class, 'device_id', 'id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DeviceMovement::class, 'device_id', 'id');
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(DeviceConnection::class, 'source_device_id', 'id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(DeviceConnection::class, 'target_device_id', 'id');
    }
}
