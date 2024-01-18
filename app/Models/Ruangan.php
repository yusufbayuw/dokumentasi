<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ruangan extends Model
{
    use HasFactory;

    //protected $appends = ['full_name'];

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

    /* public function fullName(): Attribute
    {
        return new Attribute(
            get: fn () => $this->nama . ' ' . $this->lantai->nama
        );
    } */
}
