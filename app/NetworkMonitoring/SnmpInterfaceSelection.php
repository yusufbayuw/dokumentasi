<?php

namespace App\NetworkMonitoring;

use App\Models\DeviceInterface;
use Illuminate\Support\Collection;

/**
 * Selects existing CMDB interfaces first, then creates IN-MEMORY views of
 * interfaces discovered by SNMP. These are never persisted as CMDB assets.
 */
class SnmpInterfaceSelection
{
    /** @param Collection<int, DeviceInterface> $configured
     *  @param array<int, string> $discovered map ifIndex => ifName / ifDescr
     *  @return Collection<int, DeviceInterface>
     */
    public function select(int $deviceId, Collection $configured, array $discovered, int $max): Collection
    {
        $max = max(1, min(48, $max));
        $selected = collect();
        $seen = [];

        // Prefer known CMDB interfaces that have an explicit ifIndex.
        foreach ($configured as $interface) {
            $index = (int) $interface->snmp_if_index;
            if ($index < 1 || $index > 10000000 || isset($seen[$index])) {
                continue;
            }
            $selected->push($interface);
            $seen[$index] = true;

            if ($selected->count() >= $max) {
                return $selected;
            }
        }

        ksort($discovered, SORT_NUMERIC);
        foreach ($discovered as $index => $name) {
            $index = (int) $index;
            $name = trim((string) $name);

            if ($index < 1 || $index > 10000000 || isset($seen[$index]) || $name === '') {
                continue;
            }

            // Not saved to the device_interfaces table. Missing mappings must
            // not create or modify inventory records without explicit review.
            $interface = new DeviceInterface();
            $interface->forceFill([
                'device_id' => $deviceId,
                'snmp_if_index' => $index,
                'name' => substr($name, 0, 255),
            ]);
            $selected->push($interface);
            $seen[$index] = true;

            if ($selected->count() >= $max) {
                break;
            }
        }

        return $selected;
    }
}
