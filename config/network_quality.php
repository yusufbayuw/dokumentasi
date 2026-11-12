<?php

return [
    // Opt-in: no probes or UI are activated by a code deployment alone.
    'enabled' => (bool) env('NETWORK_QUALITY_ENABLED', false),
    'interval_minutes' => max(1, (int) env('NETWORK_QUALITY_INTERVAL_MINUTES', 5)),
    'samples' => max(2, min(10, (int) env('NETWORK_QUALITY_SAMPLES', 3))),
    'timeout_seconds' => max(1, min(5, (int) env('NETWORK_QUALITY_TIMEOUT', 2))),
    'retention_days' => max(7, (int) env('NETWORK_QUALITY_RETENTION_DAYS', 14)),
    'max_jobs_per_cycle' => max(1, min(2000, (int) env('NETWORK_QUALITY_MAX_JOBS', 500))),
    'quality' => [
        'loss_degraded_pct' => 1.0,
        'loss_bad_pct' => 5.0,
        'latency_degraded_ms' => 150.0,
        'latency_bad_ms' => 350.0,
        'variation_degraded_ms' => 50.0,
        'variation_bad_ms' => 100.0,
    ],
    // Restrict active probes to trusted destinations, configurable by ICT.
    'allowed_cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NETWORK_QUALITY_ALLOWED_CIDRS', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))
    ))),
    'allowed_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NETWORK_QUALITY_ALLOWED_DOMAINS', ''))
    ))),
    'allowed_tcp_ports' => [53, 80, 443, 8080, 8443],
    'allowed_https_ports' => [443, 8443],
];
