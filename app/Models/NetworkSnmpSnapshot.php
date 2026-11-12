<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkSnmpSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'roots' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function snmpProfile(): BelongsTo
    {
        return $this->belongsTo(NetworkSnmpProfile::class, 'network_snmp_profile_id');
    }

    public function oids(): HasMany
    {
        return $this->hasMany(NetworkSnmpOid::class, 'network_snmp_snapshot_id');
    }
}
