<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NacCertificate extends Model
{
    protected $fillable = [
        'nac_client_id', 'issuer', 'serial_number',
        'valid_from', 'valid_until', 'revoked_at',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(NacClient::class, 'nac_client_id');
    }
}
