<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TypeDevice extends Model
{
    use HasFactory;

    public function device(): HasMany
    {
        return $this->hasMany(Device::class, 'type_device_id', 'id');
    }
}
