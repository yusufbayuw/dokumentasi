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

    /* public function deviceLinks(): HasMany
    {
        return $this->hasMany(DeviceLink::class, 'device_id', 'id');
    }

    public function childrenLinks(): HasMany
    {
        return $this->hasMany(DeviceLink::class, 'children_id', 'id');
    } */

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

    public function topology(): HasOne
    {
        return $this->hasOne(Topology::class, 'device_id', 'id');
    }
}
