<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NacNetwork extends Model
{
    protected $fillable = ['name', 'ssid', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function policies(): HasMany
    {
        return $this->hasMany(NacAccessPolicy::class);
    }
}
