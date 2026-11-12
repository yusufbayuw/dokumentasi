<?php

namespace App\Models;

use App\NetworkMonitoring\TargetGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class NetworkQualityTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'probe_type', 'address', 'port', 'expected_status',
        'interval_minutes', 'samples', 'timeout_seconds', 'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
        'expected_status' => 'integer',
        'interval_minutes' => 'integer',
        'samples' => 'integer',
        'timeout_seconds' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            if (! app(TargetGuard::class)->allows(
                (string) $target->probe_type,
                (string) $target->address,
                $target->port,
            )) {
                throw ValidationException::withMessages([
                    'data.address' => 'Alamat atau metode tidak diizinkan. Periksa allowlist CIDR/domain dan port di konfigurasi server.',
                ]);
            }
        });
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(NetworkQualityMeasurement::class, 'target_id');
    }
}
