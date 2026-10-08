<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NacAuthEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'nac_network_id', 'nac_client_id', 'nac_certificate_id',
        'nas_identifier', 'calling_station_id', 'decision', 'reason',
        'vlan_id', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(NacClient::class, 'nac_client_id');
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(NacNetwork::class, 'nac_network_id');
    }
}
