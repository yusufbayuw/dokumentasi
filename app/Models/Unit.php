<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    public function ruangan(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'unit_id', 'id');
    }
}
