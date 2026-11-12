<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkIncidentEvent extends Model
{
    protected $fillable = ['network_incident_id', 'event_type', 'actor_id', 'message'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(NetworkIncident::class, 'network_incident_id');
    }
}
