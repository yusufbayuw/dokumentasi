<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use SolutionForest\FilamentTree\Concern\ModelTree;

class Topology extends Model
{
    use HasFactory;
    use ModelTree;

    protected $fillable = ['parent_id', 'title', 'order', 'device_id'];
    protected $table = 'topologies';

    protected static function booted(): void
    {
        static::saving(function (Topology $topology): void {
            $topology->updateTitle();
        });

        static::saved(function (Topology $topology): void {
            $topology->syncLegacyConnection();

            if ($topology->wasChanged('device_id')) {
                static::query()
                    ->where('parent_id', $topology->id)
                    ->get()
                    ->each(fn (Topology $child) => $child->syncLegacyConnection());
            }
        });

        static::deleted(function (Topology $topology): void {
            if (! Schema::hasTable('device_connections') || ! $topology->device_id) {
                return;
            }

            DeviceConnection::query()
                ->where('target_device_id', $topology->device_id)
                ->where('source', 'topology')
                ->delete();
        });
    }

    public function updateTitle(): void
    {
        $device = $this->device;

        if (! $device) {
            $this->title = 'Topology tanpa perangkat';

            return;
        }

        $deviceTitle = $device->nama;

        if ($device->ip) {
            $deviceTitle .= ' (' . $device->ip->nama . ')';
        }

        $this->title = $this->keterangan
            ? $deviceTitle . ' : ' . $this->keterangan
            : $deviceTitle;
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topology::class, 'parent_id', 'id');
    }

    public function syncLegacyConnection(): void
    {
        if (! Schema::hasTable('device_connections') || ! $this->device_id) {
            return;
        }

        DeviceConnection::query()
            ->where('target_device_id', $this->device_id)
            ->where('source', 'topology')
            ->delete();

        if (! $this->parent_id) {
            return;
        }

        $parentDeviceId = static::query()
            ->whereKey($this->parent_id)
            ->value('device_id');

        if (! $parentDeviceId || (int) $parentDeviceId === (int) $this->device_id) {
            return;
        }

        DeviceConnection::query()->create([
            'source_device_id' => $parentDeviceId,
            'target_device_id' => $this->device_id,
            'source' => 'topology',
            'connection_type' => 'ethernet',
            'is_primary' => true,
            'status' => 'active',
            'description' => $this->keterangan,
        ]);
    }
}
