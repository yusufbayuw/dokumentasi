<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ruangan extends Model
{
    use HasFactory;

    public function device(): HasMany
    {
        return $this->hasMany(Device::class, 'ruangan_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function lantai(): BelongsTo
    {
        return $this->belongsTo(Lantai::class, 'lantai_id', 'id');
    }
}
