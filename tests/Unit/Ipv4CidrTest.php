<?php

namespace Tests\Unit;

use App\Support\Ipv4Cidr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class Ipv4CidrTest extends TestCase
{
    public function test_it_calculates_common_ipv4_subnet_fields(): void
    {
        $cidr = Ipv4Cidr::from('192.168.10.37', 24);

        $this->assertSame('255.255.255.0', $cidr->netmask);
        $this->assertSame('192.168.10.0', $cidr->network);
        $this->assertSame('192.168.10.255', $cidr->broadcast);
        $this->assertSame('192.168.10.1', $cidr->hostMin);
        $this->assertSame('192.168.10.254', $cidr->hostMax);
        $this->assertSame(254, $cidr->hostCount);
    }

    public function test_it_handles_small_managed_subnet(): void
    {
        $cidr = Ipv4Cidr::from('10.0.0.6', 30);

        $this->assertSame('10.0.0.4', $cidr->network);
        $this->assertSame('10.0.0.7', $cidr->broadcast);
        $this->assertSame('10.0.0.5', $cidr->hostMin);
        $this->assertSame('10.0.0.6', $cidr->hostMax);
        $this->assertSame(2, $cidr->hostCount);
    }

    public function test_it_detects_overlap_and_membership(): void
    {
        $a = Ipv4Cidr::from('192.168.1.0', 24);
        $b = Ipv4Cidr::from('192.168.1.128', 25);
        $c = Ipv4Cidr::from('192.168.2.0', 24);

        $this->assertTrue($a->overlaps($b));
        $this->assertFalse($a->overlaps($c));
        $this->assertTrue($a->contains('192.168.1.10'));
        $this->assertFalse($a->contains('192.168.1.255'));
    }

    public function test_it_rejects_invalid_ipv4(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ipv4Cidr::from('not-an-ip', 24);
    }
}
