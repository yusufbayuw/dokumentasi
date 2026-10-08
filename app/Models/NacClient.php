<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NacClient extends Model
{
    protected $fillable = ['user_id', 'cmdb_device_id', 'label', 'platform', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cmdbDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'cmdb_device_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(NacCertificate::class);
    }
}
