<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkProbeLocation extends Model
{
    protected $fillable = [
        'name', 'unit_id', 'isp_name', 'region', 'description',
        'enabled', 'api_token',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'enabled' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(NetworkQualityMeasurement::class, 'probe_location_id');
    }

    /**
     * The plain token is write-only. Operators must save the original safely:
     * it cannot be recovered from the stored SHA-256 digest.
     */
    protected function apiToken(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => null,
            set: fn (?string $value): array => [
                'token_hash' => filled($value) ? hash('sha256', $value) : null,
            ],
        );
    }
}
