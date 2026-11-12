<?php

namespace App\NetworkMonitoring;

class TargetGuard
{
    public function allows(string $probeType, string $address, ?int $port = null): bool
    {
        // Reject ambiguous/rewritten targets; validation must not silently
        // trim one value while the runner connects to a different value.
        if ($address !== trim($address)) {
            return false;
        }

        return match ($probeType) {
            'icmp' => $port === null && $this->allowedIp($address),
            'tcp' => $this->allowedIp($address)
                && $port !== null
                && in_array($port, config('network_quality.allowed_tcp_ports', []), true),
            'dns' => $port === null && $this->allowedDomain($address),
            'https' => $port === null && $this->allowedHttpsUrl($address),
            default => false,
        };
    }

    public function allowedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        foreach (config('network_quality.allowed_cidrs', []) as $cidr) {
            $parts = explode('/', (string) $cidr, 2);
            if (count($parts) !== 2 || filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                continue;
            }

            $prefix = filter_var($parts[1], FILTER_VALIDATE_INT);
            if ($prefix === false || $prefix < 8 || $prefix > 32) {
                continue;
            }

            $mask = (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
            if ((ip2long($ip) & $mask) === (ip2long($parts[0]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    public function allowedDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain, '. '));

        return str_contains($domain, '.')
            && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            && in_array($domain, array_map(
                fn (string $value): string => strtolower(trim($value, '. ')),
                config('network_quality.allowed_domains', []),
            ), true);
    }

    private function allowedHttpsUrl(string $url): bool
    {
        if (strlen($url) > 512 || preg_match('/[\x00-\x20]/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment'])
            && ! isset($parts['query'])
            && isset($parts['host'])
            && in_array((int) ($parts['port'] ?? 443), config('network_quality.allowed_https_ports', []), true)
            && $this->allowedDomain($parts['host']);
    }
}
