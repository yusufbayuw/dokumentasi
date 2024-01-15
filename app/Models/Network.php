<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Network extends Model
{
    use HasFactory;

    public function ip_address(): HasMany
    {
        return $this->hasMany(IpAddress::class, 'network_id', 'id');
    }
}
