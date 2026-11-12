<?php

namespace Tests\Unit;

use App\Models\DeviceInterface;
use App\NetworkMonitoring\SnmpInterfaceSelection;
use Tests\TestCase;

class SnmpInterfaceSelectionTest extends TestCase
{
    public function test_snmp_only_interfaces_are_eligible_without_mutating_cmdb(): void
    {
        $selected = (new SnmpInterfaceSelection())->select(12, collect(), [
            3 => 'ether3',
            1 => 'ether1',
        ], 10);

        $this->assertCount(2, $selected);
        $this->assertSame([1, 3], $selected->pluck('snmp_if_index')->all());
        $this->assertSame(['ether1', 'ether3'], $selected->pluck('name')->all());
        $this->assertTrue($selected->every(fn (DeviceInterface $port): bool => ! $port->exists));
        $this->assertTrue($selected->every(fn (DeviceInterface $port): bool => $port->device_id === 12));
    }

    public function test_existing_cmdb_ports_have_priority_and_no_duplicate_ifindex_is_created(): void
    {
        $existing = new DeviceInterface();
        $existing->forceFill(['id' => 9, 'device_id' => 12, 'name' => 'uplink', 'snmp_if_index' => 2]);
        $existing->exists = true;

        $selected = (new SnmpInterfaceSelection())->select(12, collect([$existing]), [
            2 => 'ether2',
            3 => 'ether3',
            5 => 'ether5',
        ], 2);

        $this->assertSame([2, 3], $selected->pluck('snmp_if_index')->all());
        $this->assertSame(['uplink', 'ether3'], $selected->pluck('name')->all());
        $this->assertTrue($selected->first()->exists);
        $this->assertFalse($selected->last()->exists);
    }

    public function test_invalid_empty_and_out_of_range_discoveries_are_ignored(): void
    {
        $selected = (new SnmpInterfaceSelection())->select(12, collect(), [
            0 => 'invalid',
            1 => '',
            20000000 => 'huge',
            4 => 'port4',
        ], 10);

        $this->assertSame([4], $selected->pluck('snmp_if_index')->all());
    }
}
