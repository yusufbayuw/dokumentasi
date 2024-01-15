<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gedung extends Model
{
    use HasFactory;

    public function lantai(): HasMany
    {
        return $this->hasMany(Lantai::class, 'gedung_id', 'id');
    }
}
