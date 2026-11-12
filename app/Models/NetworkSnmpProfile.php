<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkSnmpProfile extends Model
{
    protected $fillable = [
        'device_id', 'security_name', 'auth_protocol', 'auth_passphrase',
        'privacy_passphrase', 'enabled',
    ];

    protected $hidden = ['security_name', 'auth_passphrase', 'privacy_passphrase'];

    protected $casts = [
        'security_name' => 'encrypted',
        'auth_passphrase' => 'encrypted',
        'privacy_passphrase' => 'encrypted',
        'enabled' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
