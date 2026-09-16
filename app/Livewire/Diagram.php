<?php

namespace App\Livewire;

use App\Filament\Resources\DeviceResource;
use App\Models\Topology;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Diagram extends Component
{
    public function render()
    {
        $topologies = Topology::query()
            ->with([
                'device.ip',
                'device.type',
                'device.ruangan.unit',
                'device.ruangan.lantai.gedung',
            ])
            ->get();

        $childrenCounts = $topologies
            ->whereNotNull('parent_id')
            ->countBy('parent_id');

        $nodeDataArray = $topologies
            ->filter(fn (Topology $topology): bool => $topology->device !== null)
            ->map(function (Topology $topology) use ($childrenCounts): array {
                $device = $topology->device;
                $ip = $device->ip;
                $room = $device->ruangan;
                $floor = $room?->lantai;
                $building = $floor?->gedung;
                $unit = $room?->unit;

                $status = ! $ip
                    ? 'unknown'
                    : ($ip->status ? 'online' : 'offline');

                $locationParts = array_filter([
                    $room?->nama,
                    $floor?->nama,
                    $building?->nama,
                ]);

                return [
                    'key' => $topology->id,
                    'parent' => $topology->parent_id,
                    'deviceId' => $device->id,
                    'name' => $device->nama,
                    'type' => $device->type?->nama ?? 'Tanpa Jenis',
                    'typeCode' => $this->typeCode($device->type?->nama),
                    'ip' => $ip?->nama,
                    'status' => $status,
                    'statusLabel' => match ($status) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                        default => 'Tidak diketahui',
                    },
                    'room' => $room?->nama,
                    'floor' => $floor?->nama,
                    'building' => $building?->nama,
                    'unit' => $unit?->nama,
                    'location' => $locationParts !== [] ? implode(' • ', $locationParts) : null,
                    'description' => $topology->keterangan ?: $device->keterangan,
                    'childrenCount' => $childrenCounts->get($topology->id, 0),
                    'editUrl' => Gate::allows('update', $device)
                        ? DeviceResource::getUrl('edit', ['record' => $device])
                        : null,
                ];
            })
            ->values()
            ->all();

        $types = collect($nodeDataArray)
            ->pluck('type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $units = collect($nodeDataArray)
            ->pluck('unit')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $stats = [
            'total' => count($nodeDataArray),
            'online' => collect($nodeDataArray)->where('status', 'online')->count(),
            'offline' => collect($nodeDataArray)->where('status', 'offline')->count(),
            'unknown' => collect($nodeDataArray)->where('status', 'unknown')->count(),
        ];

        return view('livewire.diagram', [
            'nodeDataArray' => $nodeDataArray,
            'types' => $types,
            'units' => $units,
            'stats' => $stats,
        ]);
    }

    private function typeCode(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return match (true) {
            str_contains($normalized, 'access') || str_contains($normalized, 'ap') => 'AP',
            str_contains($normalized, 'switch') => 'SW',
            str_contains($normalized, 'router') => 'RT',
            str_contains($normalized, 'server') => 'SV',
            str_contains($normalized, 'finger') => 'FP',
            str_contains($normalized, 'cctv') || str_contains($normalized, 'camera') => 'CAM',
            str_contains($normalized, 'printer') => 'PR',
            default => 'DEV',
        };
    }
}
