<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceLink extends Model
{
    use HasFactory;

    public function deviceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function deviceChildren(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'children_id', 'id');
    }
}
