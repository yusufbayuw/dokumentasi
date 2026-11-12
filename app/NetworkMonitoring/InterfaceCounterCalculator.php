<?php

namespace App\NetworkMonitoring;

use App\Models\NetworkInterfaceSample;

class InterfaceCounterCalculator
{
    /** @return array{rx_mbps:?float,tx_mbps:?float,in_errors_delta:?int,out_errors_delta:?int} */
    public function calculate(
        ?NetworkInterfaceSample $previous,
        array $current,
        \DateTimeInterface $checkedAt,
        ?int $speedMbps = null,
    ): array {
        $empty = [
            'rx_mbps' => null,
            'tx_mbps' => null,
            'in_errors_delta' => null,
            'out_errors_delta' => null,
        ];
        if (! $previous || $previous->error_code !== null
            || $previous->in_octets === null || $previous->out_octets === null) {
            return $empty;
        }

        $elapsed = $checkedAt->getTimestamp() - $previous->checked_at->getTimestamp();
        if ($elapsed < 1 || $elapsed > 3600) {
            return $empty;
        }

        $rx = self::delta($previous->in_octets, $current['in_octets'] ?? null);
        $tx = self::delta($previous->out_octets, $current['out_octets'] ?? null);
        $inErrors = self::delta($previous->in_errors, $current['in_errors'] ?? null);
        $outErrors = self::delta($previous->out_errors, $current['out_errors'] ?? null);

        // Unexplained counter drops/reboots are not interpreted as zero traffic.
        if ($rx === null || $tx === null) {
            return $empty;
        }

        $rxMbps = round($rx * 8 / $elapsed / 1000000, 3);
        $txMbps = round($tx * 8 / $elapsed / 1000000, 3);

        // Discard discontinuities, wraps and implausible counter jumps.
        if ($speedMbps && ($rxMbps > $speedMbps * 1.25 || $txMbps > $speedMbps * 1.25)) {
            return $empty;
        }

        return [
            'rx_mbps' => $rxMbps,
            'tx_mbps' => $txMbps,
            'in_errors_delta' => $inErrors,
            'out_errors_delta' => $outErrors,
        ];
    }

    public static function counter(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim((string) $value);
        // Allow Net-SNMP typed output as well as SNMP_VALUE_PLAIN.
        if (preg_match('/^(?:Counter64|Counter32|INTEGER|Gauge32):\s*(\d+)$/i', $text, $matches)) {
            $text = $matches[1];
        }

        if (! preg_match('/^\d+$/', $text)) {
            return null;
        }

        $normalized = ltrim($text, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $limit = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($limit)
            || (strlen($normalized) === strlen($limit) && strcmp($normalized, $limit) > 0)) {
            // PHP integers cannot faithfully represent this unsigned counter.
            return null;
        }

        return (int) $normalized;
    }

    public static function delta(mixed $previous, mixed $current): ?int
    {
        $before = self::counter($previous);
        $after = self::counter($current);

        return $before !== null && $after !== null && $after >= $before
            ? $after - $before
            : null;
    }
}
