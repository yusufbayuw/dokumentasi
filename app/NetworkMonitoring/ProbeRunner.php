<?php

namespace App\NetworkMonitoring;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class ProbeRunner
{
    /**
     * All probes return one normalized, non-secret result. No command is built
     * through a shell, and redirect-following is disabled for HTTPS checks.
     */
    public function run(
        string $type,
        string $address,
        ?int $port = null,
        int $samples = 3,
        int $timeoutSeconds = 2,
        ?int $expectedStatus = null,
    ): array {
        $result = [
            'status' => 'error',
            'latency_ms' => null,
            'packet_loss_pct' => null,
            'rtt_variation_ms' => null,
            'samples_sent' => null,
            'samples_received' => null,
            'response_code' => null,
            'error_code' => null,
        ];
        $started = microtime(true);

        $samples = max(2, min(10, $samples));
        $timeoutSeconds = max(1, min(5, $timeoutSeconds));

        if (! app(TargetGuard::class)->allows($type, $address, $port)) {
            $result['error_code'] = 'destination_not_allowed';
        } else {
            try {
                $details = match ($type) {
                    'icmp' => $this->icmp($address, $samples, $timeoutSeconds),
                    'dns' => $this->dns($address),
                    'tcp' => $this->tcp($address, (int) $port, $timeoutSeconds),
                    'https' => $this->https($address, $timeoutSeconds, $expectedStatus),
                    default => ['status' => 'error', 'error_code' => 'unsupported_probe'],
                };

                $result = array_replace($result, $details);
            } catch (Throwable) {
                // Never persist exception messages: they may embed URLs or secrets.
                $result['error_code'] = 'probe_exception';
            }
        }

        $result['duration_ms'] = (int) min(4294967295, max(0, round((microtime(true) - $started) * 1000)));

        return $result;
    }

    private function icmp(string $ip, int $samples, int $timeout): array
    {
        $process = new Process([
            'ping', '-n', '-c', (string) $samples,
            '-W', (string) $timeout, '-i', '0.2', $ip,
        ]);
        $process->setTimeout(min(60, $samples * ($timeout + 1) + 5));
        $process->run();

        return self::parseIcmpOutput($process->getOutput() . "\n" . $process->getErrorOutput());
    }

    public static function parseIcmpOutput(string $output): array
    {
        if (! preg_match('/(\d+)\s+packets transmitted,\s+(\d+)\s+(?:packets\s+)?received/i', $output, $counts)) {
            return ['status' => 'error', 'error_code' => 'icmp_unavailable'];
        }

        $sent = (int) $counts[1];
        $received = (int) $counts[2];
        if ($sent < 1 || $received > $sent) {
            return ['status' => 'error', 'error_code' => 'invalid_icmp_result'];
        }

        preg_match_all('/\btime[=<]\s*([0-9]+(?:\.[0-9]+)?)\s*ms/i', $output, $matches);
        $times = array_map('floatval', $matches[1] ?? []);
        $variations = [];
        for ($i = 1; $i < count($times); $i++) {
            $variations[] = abs($times[$i] - $times[$i - 1]);
        }

        return [
            'status' => $received > 0 ? 'up' : 'down',
            'samples_sent' => $sent,
            'samples_received' => $received,
            'packet_loss_pct' => round(100 * ($sent - $received) / $sent, 2),
            'latency_ms' => $times !== [] ? round(array_sum($times) / count($times), 2) : null,
            // RTT variation is not an RTP/one-way jitter measurement.
            'rtt_variation_ms' => $variations !== [] ? round(array_sum($variations) / count($variations), 2) : null,
        ];
    }

    private function dns(string $domain): array
    {
        $start = microtime(true);
        $records = @dns_get_record($domain, DNS_A);

        return [
            'status' => is_array($records) && $records !== [] ? 'up' : 'down',
            'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            'error_code' => $records === false ? 'dns_lookup_failed' : null,
        ];
    }

    private function tcp(string $ip, int $port, int $timeout): array
    {
        $start = microtime(true);
        $socket = @stream_socket_client(
            'tcp://' . $ip . ':' . $port,
            $errorNumber,
            $errorString,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );
        $connected = is_resource($socket);
        if ($connected) {
            fclose($socket);
        }

        return [
            'status' => $connected ? 'up' : 'down',
            'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            'error_code' => $connected ? null : 'tcp_connect_failed',
        ];
    }

    private function https(string $url, int $timeout, ?int $expectedStatus): array
    {
        $start = microtime(true);
        try {
            $response = Http::withOptions(['allow_redirects' => false, 'proxy' => ''])
                ->connectTimeout($timeout)
                ->timeout($timeout)
                ->get($url);
        } catch (Throwable) {
            return ['status' => 'down', 'error_code' => 'https_request_failed'];
        }

        $statusCode = $response->status();
        $ok = $expectedStatus !== null
            ? $statusCode === $expectedStatus
            : ($statusCode >= 200 && $statusCode < 400);

        return [
            'status' => $ok ? 'up' : 'down',
            'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            'response_code' => $statusCode,
            'error_code' => $ok ? null : 'unexpected_http_status',
        ];
    }
}
