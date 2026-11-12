<?php

namespace Tests\Unit;

use App\NetworkMonitoring\ProbeRunner;
use App\NetworkMonitoring\TargetGuard;
use Tests\TestCase;

class NetworkQualityProbeTest extends TestCase
{
    public function test_icmp_parser_handles_success_partial_loss_and_failure(): void
    {
        $ok = ProbeRunner::parseIcmpOutput(
            "64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=10.0 ms\n".
            "64 bytes from 10.0.0.1: icmp_seq=2 ttl=64 time=14.0 ms\n".
            "3 packets transmitted, 2 received, 33.333% packet loss"
        );

        $this->assertSame('up', $ok['status']);
        $this->assertSame(3, $ok['samples_sent']);
        $this->assertSame(2, $ok['samples_received']);
        $this->assertSame(33.33, $ok['packet_loss_pct']);
        $this->assertSame(12.0, $ok['latency_ms']);
        $this->assertSame(4.0, $ok['rtt_variation_ms']);

        $down = ProbeRunner::parseIcmpOutput('3 packets transmitted, 0 received, 100% packet loss');
        $this->assertSame('down', $down['status']);
        $this->assertSame(100.0, $down['packet_loss_pct']);
        $this->assertNull($down['latency_ms']);

        $error = ProbeRunner::parseIcmpOutput('ping: command not found');
        $this->assertSame('error', $error['status']);
        $this->assertSame('icmp_unavailable', $error['error_code']);
    }

    public function test_target_guard_only_allows_explicit_destinations(): void
    {
        config([
            'network_quality.allowed_cidrs' => ['10.10.0.0/16'],
            'network_quality.allowed_domains' => ['example.org'],
            'network_quality.allowed_tcp_ports' => [443],
            'network_quality.allowed_https_ports' => [443],
        ]);

        $guard = app(TargetGuard::class);

        $this->assertTrue($guard->allows('icmp', '10.10.1.8'));
        $this->assertFalse($guard->allows('icmp', '127.0.0.1'));
        $this->assertFalse($guard->allows('icmp', '8.8.8.8'));
        $this->assertTrue($guard->allows('tcp', '10.10.1.8', 443));
        $this->assertFalse($guard->allows('tcp', '10.10.1.8', 25));
        $this->assertTrue($guard->allows('dns', 'example.org'));
        $this->assertFalse($guard->allows('dns', 'api.example.org'));
        $this->assertTrue($guard->allows('https', 'https://example.org/health'));
        $this->assertFalse($guard->allows('https', 'http://example.org/health'));
        $this->assertFalse($guard->allows('https', 'https://example.org:8080/'));
        $this->assertFalse($guard->allows('https', 'https://user:password@example.org/'));
    }

    public function test_rejected_targets_never_run_a_probe(): void
    {
        config(['network_quality.allowed_cidrs' => []]);

        $result = app(ProbeRunner::class)->run('icmp', '127.0.0.1');
        $this->assertSame('error', $result['status']);
        $this->assertSame('destination_not_allowed', $result['error_code']);
    }
}
