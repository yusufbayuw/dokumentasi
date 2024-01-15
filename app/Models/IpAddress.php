<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAddress extends Model
{
    use HasFactory;

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'id');
    }
}
