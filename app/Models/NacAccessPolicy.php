<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NacAccessPolicy extends Model
{
    protected $fillable = [
        'nac_network_id', 'name', 'role_name', 'vlan_id', 'priority', 'enabled',
    ];

    protected $casts = [
        'vlan_id' => 'integer',
        'priority' => 'integer',
        'enabled' => 'boolean',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(NacNetwork::class, 'nac_network_id');
    }
}
