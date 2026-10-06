<?php

namespace App\Livewire;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\DeviceConnection;
use App\Services\DeviceHealthService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Diagram extends Component
{
    public function render()
    {
        $devices = Device::query()
            ->with([
                'ip',
                'type',
                'ownerUnit',
                'ruangan.unit',
                'ruangan.lantai.gedung',
            ])
            ->get();

        $deviceIds = $devices->pluck('id')->all();

        $connections = DeviceConnection::query()
            ->with(['sourceInterface', 'targetInterface'])
            ->whereIn('source_device_id', $deviceIds)
            ->whereIn('target_device_id', $deviceIds)
            ->get();

        $degreeByDevice = collect($deviceIds)
            ->mapWithKeys(fn (int $id): array => [$id => [
                'incoming' => 0,
                'outgoing' => 0,
            ]]);

        foreach ($connections as $connection) {
            if ($degreeByDevice->has($connection->source_device_id)) {
                $value = $degreeByDevice->get($connection->source_device_id);
                $value['outgoing']++;
                $degreeByDevice->put($connection->source_device_id, $value);
            }

            if ($degreeByDevice->has($connection->target_device_id)) {
                $value = $degreeByDevice->get($connection->target_device_id);
                $value['incoming']++;
                $degreeByDevice->put($connection->target_device_id, $value);
            }
        }

        $nodes = $devices
            ->map(function (Device $device) use ($degreeByDevice): array {
                $ip = $device->ip;
                $room = $device->ruangan;
                $floor = $room?->lantai;
                $building = $floor?->gedung;
                $locationUnit = $room?->unit;
                $ownerUnit = $device->ownerUnit;
                $degree = $degreeByDevice->get($device->id, ['incoming' => 0, 'outgoing' => 0]);

                $status = app(DeviceHealthService::class)->status($device);

                $locationParts = array_filter([
                    $room?->nama,
                    $floor?->nama,
                    $building?->nama,
                ]);

                return [
                    'id' => 'device-' . $device->id,
                    'deviceId' => $device->id,
                    'name' => $device->nama,
                    'assetCode' => $device->asset_code,
                    'hostname' => $device->hostname,
                    'type' => $device->type?->nama ?? 'Tanpa Jenis',
                    'typeCode' => $this->typeCode($device->type?->nama),
                    'ip' => $ip?->nama,
                    'status' => $status,
                    'statusLabel' => app(DeviceHealthService::class)->label($status),
                    'criticality' => $device->criticality ?? 'normal',
                    'criticalityLabel' => $this->criticalityLabel($device->criticality),
                    'lifecycle' => $device->lifecycle_status ?? 'active',
                    'lifecycleLabel' => $this->lifecycleLabel($device->lifecycle_status),
                    'monitoringEnabled' => (bool) $device->monitoring_enabled,
                    'latency' => $device->last_latency_ms !== null ? (float) $device->last_latency_ms : null,
                    'lastSeenAt' => $device->last_seen_at?->format('d/m/Y H:i:s'),
                    'lastCheckedAt' => $device->last_checked_at?->format('d/m/Y H:i:s'),
                    'room' => $room?->nama,
                    'floor' => $floor?->nama,
                    'building' => $building?->nama,
                    'unit' => $locationUnit?->nama,
                    'ownerUnit' => $ownerUnit?->nama,
                    'location' => $locationParts !== [] ? implode(' • ', $locationParts) : null,
                    'description' => $device->keterangan,
                    'incomingCount' => $degree['incoming'],
                    'outgoingCount' => $degree['outgoing'],
                    'connectionCount' => $degree['incoming'] + $degree['outgoing'],
                    'editUrl' => Gate::allows('update', $device)
                        ? DeviceResource::getUrl('edit', ['record' => $device])
                        : null,
                ];
            })
            ->values()
            ->all();

        $edges = $connections
            ->map(fn (DeviceConnection $connection): array => [
                'id' => 'connection-' . $connection->id,
                'connectionId' => $connection->id,
                'source' => 'device-' . $connection->source_device_id,
                'target' => 'device-' . $connection->target_device_id,
                'origin' => $connection->source,
                'connectionType' => $connection->connection_type,
                'sourcePort' => $connection->sourceInterface?->name ?? $connection->source_port,
                'targetPort' => $connection->targetInterface?->name ?? $connection->target_port,
                'speed' => $connection->speed,
                'vlan' => $connection->vlan,
                'isPrimary' => (bool) $connection->is_primary,
                'status' => $connection->status,
                'label' => $connection->label,
                'description' => $connection->description,
            ])
            ->values()
            ->all();

        $types = collect($nodes)
            ->pluck('type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $units = collect($nodes)
            ->pluck('unit')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $stats = [
            'total' => count($nodes),
            'connections' => count($edges),
            'online' => collect($nodes)->where('status', 'online')->count(),
            'offline' => collect($nodes)->where('status', 'offline')->count(),
            'unknown' => collect($nodes)->where('status', 'unknown')->count(),
        ];

        return view('livewire.diagram', [
            'nodes' => $nodes,
            'edges' => $edges,
            'types' => $types,
            'units' => $units,
            'stats' => $stats,
        ]);
    }

    private function typeCode(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return match (true) {
            str_contains($normalized, 'access point'),
            str_contains($normalized, 'accesspoint'),
            $normalized === 'ap',
            str_contains($normalized, 'wireless') => 'AP',
            str_contains($normalized, 'switch') => 'SW',
            str_contains($normalized, 'router') => 'RT',
            str_contains($normalized, 'firewall') => 'FW',
            str_contains($normalized, 'server') => 'SV',
            str_contains($normalized, 'finger') => 'FP',
            str_contains($normalized, 'cctv'),
            str_contains($normalized, 'camera') => 'CAM',
            str_contains($normalized, 'printer') => 'PR',
            default => 'DEV',
        };
    }

    private function criticalityLabel(?string $criticality): string
    {
        return match ($criticality) {
            'critical' => 'Critical',
            'high' => 'High',
            'low' => 'Low',
            default => 'Normal',
        };
    }

    private function lifecycleLabel(?string $status): string
    {
        return match ($status) {
            'spare' => 'Cadangan',
            'repair' => 'Perbaikan',
            'retired' => 'Pensiun',
            'disposed' => 'Dihapuskan',
            default => 'Aktif',
        };
    }
}
