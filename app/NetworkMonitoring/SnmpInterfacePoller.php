<?php

namespace App\NetworkMonitoring;

use App\Models\Device;
use App\Models\DeviceInterface;
use App\Models\NetworkInterfaceSample;
use App\Models\NetworkSnmpProfile;
use Illuminate\Support\Collection;
use Throwable;

class SnmpInterfacePoller
{
    public const OIDS = [
        'in_octets' => '1.3.6.1.2.1.31.1.1.1.6',
        'out_octets' => '1.3.6.1.2.1.31.1.1.1.10',
        'in_errors' => '1.3.6.1.2.1.2.2.1.14',
        'out_errors' => '1.3.6.1.2.1.2.2.1.20',
        'oper_status' => '1.3.6.1.2.1.2.2.1.8',
        'speed_mbps' => '1.3.6.1.2.1.31.1.1.1.15',
    ];

    public function __construct(
        private NativeSnmpTransport $transport,
        private InterfaceCounterCalculator $calculator,
        private TargetGuard $guard,
    ) {
    }

    public function poll(int $deviceId): void
    {
        $device = Device::query()
            ->with(['ip', 'managementInterface.ipAddress', 'snmpProfile'])
            ->find($deviceId);

        if (! $device || ! $device->monitoring_enabled
            || in_array($device->lifecycle_status, ['retired', 'disposed'], true)) {
            return;
        }

        $profile = $device->snmpProfile;
        if (! $profile?->enabled) {
            return;
        }

        $host = $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama;
        if (! $host || ! $this->guard->allows('icmp', $host)) {
            return; // Same explicit allowed CIDRs used by the ICMP collector.
        }

        // Map existing CMDB interfaces automatically by a unique exact ifName
        // match. Never auto-create, delete or reassign unrelated interfaces.
        if ($this->transport->available()
            && $device->interfaces()->whereNull('snmp_if_index')->exists()) {
            try {
                $this->mapExistingInterfaces($device, $this->transport->interfaceNames($profile, $host));
            } catch (Throwable) {
                // Optional discovery can fail on devices without IF-MIB support.
            }
        }

        $interfaces = $device->interfaces()
            ->whereNotNull('snmp_if_index')
            ->orderBy('id')
            ->limit((int) config('network_quality.snmp.max_interfaces_per_device', 16))
            ->get();

        foreach ($interfaces->chunk(6) as $batch) {
            $this->pollBatch($device, $profile, $host, $batch);
        }
    }

    /** @param array<int,string> $names */
    private function mapExistingInterfaces(Device $device, array $names): void
    {
        $local = $device->interfaces()->get(['id', 'device_id', 'name', 'snmp_if_index']);
        $known = $local->pluck('snmp_if_index')->filter()->map(fn ($n): int => (int) $n)->all();
        $remote = [];

        foreach ($names as $index => $name) {
            $key = strtolower(trim($name));
            if ($key !== '' && $index > 0) {
                $remote[$key][] = $index;
            }
        }

        foreach ($local as $interface) {
            if ($interface->snmp_if_index !== null) {
                continue;
            }

            $key = strtolower(trim($interface->name));
            $sameNames = $local->filter(fn ($item): bool => strtolower(trim($item->name)) === $key);
            $candidates = $remote[$key] ?? [];

            if ($key === '' || $sameNames->count() !== 1 || count($candidates) !== 1
                || in_array($candidates[0], $known, true)) {
                continue;
            }

            $interface->update(['snmp_if_index' => $candidates[0]]);
            $known[] = $candidates[0];
        }
    }

    /** @param Collection<int, DeviceInterface> $batch */
    private function pollBatch(
        Device $device,
        NetworkSnmpProfile $profile,
        string $host,
        Collection $batch,
    ): void {
        $oids = [];
        foreach ($batch as $interface) {
            foreach (self::OIDS as $prefix) {
                $oids[] = '.'.$prefix.'.'.$interface->snmp_if_index;
            }
        }

        try {
            $values = $this->transport->read($profile, $host, $oids);
        } catch (Throwable) {
            foreach ($batch as $interface) {
                $this->unknown($device, $interface, $this->transport->available()
                    ? 'snmp_read_failed'
                    : 'snmp_extension_missing');
            }

            return;
        }

        foreach ($batch as $interface) {
            $index = (int) $interface->snmp_if_index;
            $result = [];
            foreach (self::OIDS as $key => $prefix) {
                $result[$key] = InterfaceCounterCalculator::counter(
                    $values[$prefix.'.'.$index] ?? null
                );
            }

            $status = match ($result['oper_status']) {
                1 => 'up',
                2 => 'down',
                default => 'unknown',
            };
            $error = $result['in_octets'] === null || $result['out_octets'] === null
                ? 'snmp_64bit_counter_missing'
                : null;
            $checkedAt = now();
            $speed = $result['speed_mbps'] ?: $interface->speed_mbps;

            $previous = NetworkInterfaceSample::query()
                ->where('device_interface_id', $interface->id)
                ->where('snmp_if_index', $index)
                ->orderByDesc('checked_at')
                ->orderByDesc('id')
                ->first();

            $delta = $status === 'up' && $error === null
                ? $this->calculator->calculate($previous, $result, $checkedAt, $speed)
                : $this->calculator->calculate(null, [], $checkedAt);

            NetworkInterfaceSample::create([
                'device_id' => $device->id,
                'device_interface_id' => $interface->id,
                'interface_name' => $interface->name,
                'snmp_if_index' => $index,
                'status' => $status,
                'in_octets' => $result['in_octets'],
                'out_octets' => $result['out_octets'],
                'in_errors' => $result['in_errors'],
                'out_errors' => $result['out_errors'],
                'in_errors_delta' => $delta['in_errors_delta'],
                'out_errors_delta' => $delta['out_errors_delta'],
                'rx_mbps' => $delta['rx_mbps'],
                'tx_mbps' => $delta['tx_mbps'],
                'speed_mbps' => $speed,
                'error_code' => $error,
                'checked_at' => $checkedAt,
            ]);
        }
    }

    private function unknown(Device $device, DeviceInterface $interface, string $code): void
    {
        NetworkInterfaceSample::create([
            'device_id' => $device->id,
            'device_interface_id' => $interface->id,
            'interface_name' => $interface->name,
            'snmp_if_index' => $interface->snmp_if_index,
            'status' => 'unknown',
            'error_code' => $code,
            'checked_at' => now(),
        ]);
    }
}
