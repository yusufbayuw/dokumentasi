<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMovement extends Model
{
    use HasFactory;

    protected $casts = [
        'moved_at' => 'datetime',
        'automatic' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'from_ruangan_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'to_ruangan_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
