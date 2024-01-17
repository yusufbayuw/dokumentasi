<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SolutionForest\FilamentTree\Concern\ModelTree;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Topology extends Model
{
    use ModelTree;

    protected $fillable = ["parent_id", "title", "order", "device_id"];
    protected $table = 'topologies';

    public static function boot()
    {
        parent::boot();

        static::saving(function ($topology) {
            $topology->updateTitle();
        });
    }

    public function updateTitle()
    {
        if ($this->keterangan) {
            if ($this->device->ip)
            {
                $this->title = $this->device->nama .' ('. $this->device->ip->nama . ') : ' . $this->keterangan;
            } else {
                $this->title = $this->device->nama . ' : ' . $this->keterangan;
            }
        } else {
            if ($this->device->ip){
                $this->title = $this->device->nama .' ('. $this->device->ip->nama .')';
            } else {
                $this->title = $this->device->nama;
            } 
        }
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topology::class, 'parent_id', 'id');
    }
}
