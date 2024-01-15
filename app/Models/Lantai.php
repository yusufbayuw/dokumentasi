<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lantai extends Model
{
    use HasFactory;

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'lantai_id', 'id');
    }

    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class, 'gedung_id', 'id');
    }
}
