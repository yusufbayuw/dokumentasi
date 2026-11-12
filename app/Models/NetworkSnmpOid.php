<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkSnmpOid extends Model
{
    protected $guarded = [];

    protected $hidden = ['value'];

    protected $casts = [
        'value' => 'encrypted',
        'truncated' => 'boolean',
        'raw_bytes' => 'integer',
        'value_type' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(NetworkSnmpSnapshot::class, 'network_snmp_snapshot_id');
    }

    public function displayValue(): string
    {
        return $this->encoding === 'base64'
            ? '[Biner/Base64] '.$this->value
            : (string) $this->value;
    }
}
