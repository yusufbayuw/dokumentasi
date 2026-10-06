<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class DeviceCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'credential_type',
        'username_encrypted',
        'secret_encrypted',
        'rotated_at',
        'expires_at',
    ];

    protected $hidden = [
        'username_encrypted',
        'secret_encrypted',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => filled($this->username_encrypted)
                ? Crypt::decryptString($this->username_encrypted)
                : null,
            set: fn (?string $value): array => [
                'username_encrypted' => filled($value) ? Crypt::encryptString($value) : null,
            ],
        );
    }

    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => filled($this->secret_encrypted)
                ? Crypt::decryptString($this->secret_encrypted)
                : null,
            set: fn (?string $value): array => [
                'secret_encrypted' => filled($value) ? Crypt::encryptString($value) : null,
            ],
        );
    }
}
