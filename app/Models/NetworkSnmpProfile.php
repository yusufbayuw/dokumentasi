<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkSnmpProfile extends Model
{
    protected $fillable = [
        'device_id', 'version', 'community',
        'security_name', 'auth_protocol', 'auth_passphrase',
        'privacy_passphrase', 'enabled',
    ];

    protected $hidden = ['security_name', 'auth_passphrase', 'privacy_passphrase', 'community'];

    protected $casts = [
        'community' => 'encrypted',
        'security_name' => 'encrypted',
        'auth_passphrase' => 'encrypted',
        'privacy_passphrase' => 'encrypted',
        'enabled' => 'boolean',
        'last_polled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A device uses exactly one SNMP protocol. When changing versions,
        // remove the previously used secret(s) rather than leaving dormant
        // credentials behind in the database.
        static::saving(function (self $profile): void {
            if ($profile->version === '2c') {
                $profile->security_name = null;
                $profile->auth_passphrase = null;
                $profile->privacy_passphrase = null;
            } elseif ($profile->version === '3') {
                $profile->community = null;
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
