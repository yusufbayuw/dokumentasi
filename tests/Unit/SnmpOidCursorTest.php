<?php

namespace Tests\Unit;

use App\NetworkMonitoring\SnmpOidCursor;
use PHPUnit\Framework\TestCase;

class SnmpOidCursorTest extends TestCase
{
    public function test_numeric_oid_boundary_is_exact(): void
    {
        $this->assertTrue(SnmpOidCursor::within('1.3.6.1.2.1.1', '1.3.6.1'));
        $this->assertFalse(SnmpOidCursor::within('1.3.6.10.1', '1.3.6.1'));
        $this->assertFalse(SnmpOidCursor::within('1.3.6.11', '1.3.6.1'));
    }

    public function test_numeric_ordering_is_not_string_order(): void
    {
        $this->assertTrue(SnmpOidCursor::greaterThan('1.3.6.1.10', '1.3.6.1.9'));
        $this->assertFalse(SnmpOidCursor::greaterThan('1.3.6.1.2', '1.3.6.1.10'));
        $this->assertFalse(SnmpOidCursor::greaterThan('1.3.6.1.9', '1.3.6.1.9'));
    }

    public function test_normalization_and_bounded_roots(): void
    {
        $this->assertSame('1.3.6.1.2.1', SnmpOidCursor::normalize('.01.3.6.1.2.1'));
        $this->assertSame(['1.3.6.1', '1.3.6.1.4.1'], SnmpOidCursor::validRoots([
            '.1.3.6.1',
            '1.3.6.1.4.1',
            '1.3.6.1',
            '1.3.6.10',
            'insecure',
        ]));
        $this->assertNull(SnmpOidCursor::normalize('1.3.6.1; rm -rf /'));
    }
}
