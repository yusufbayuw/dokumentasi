<?php

namespace Tests\Unit;

use App\Models\NetworkInterfaceSample;
use App\NetworkMonitoring\InterfaceCounterCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class NetworkInterfaceCounterCalculatorTest extends TestCase
{
    public function test_it_derives_rates_from_elapsed_time_and_counter_deltas(): void
    {
        $previous = new NetworkInterfaceSample();
        $previous->in_octets = 1000000;
        $previous->out_octets = 2000000;
        $previous->in_errors = 3;
        $previous->out_errors = 5;
        $previous->checked_at = Carbon::parse('2026-11-12 00:00:00');
        $previous->error_code = null;

        $delta = (new InterfaceCounterCalculator())->calculate($previous, [
            'in_octets' => 2000000,
            'out_octets' => 3000000,
            'in_errors' => 4,
            'out_errors' => 9,
        ], Carbon::parse('2026-11-12 00:01:00'), 100);

        $this->assertSame(0.133, $delta['rx_mbps']);
        $this->assertSame(0.133, $delta['tx_mbps']);
        $this->assertSame(1, $delta['in_errors_delta']);
        $this->assertSame(4, $delta['out_errors_delta']);
    }

    public function test_first_sample_wrap_and_inconsistent_jump_have_no_rate(): void
    {
        $calculator = new InterfaceCounterCalculator();
        $this->assertNull($calculator->calculate(null, [], Carbon::now())['rx_mbps']);

        $previous = new NetworkInterfaceSample();
        $previous->in_octets = 90000;
        $previous->out_octets = 90000;
        $previous->checked_at = Carbon::parse('2026-11-12 00:00:00');

        $reset = $calculator->calculate($previous, [
            'in_octets' => 0, 'out_octets' => 0,
        ], Carbon::parse('2026-11-12 00:01:00'));
        $this->assertNull($reset['rx_mbps']);

        $jump = $calculator->calculate($previous, [
            'in_octets' => 99999999999, 'out_octets' => 99999999999,
        ], Carbon::parse('2026-11-12 00:01:00'), 100);
        $this->assertNull($jump['rx_mbps']);
    }

    public function test_oversized_or_invalid_64_bit_counters_are_not_silently_wrapped(): void
    {
        $this->assertNull(InterfaceCounterCalculator::counter('18446744073709551615'));
        $this->assertSame(4294967296, InterfaceCounterCalculator::counter('Counter64: 4294967296'));
        $this->assertNull(InterfaceCounterCalculator::counter('noSuchInstance'));
    }
}
