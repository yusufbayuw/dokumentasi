# Network Quality Monitoring (Fase 0-3)

This opt-in module is intentionally independent of the legacy device ping.
It adds ICMP multi-sample RTT/packet-loss/RTT variation, DNS A lookups, TCP
connectivity, HTTPS status checks, an operational dashboard and service targets.

## Safety and activation

- By default `NETWORK_QUALITY_ENABLED=false`. Migration is additive; it
  does **not** change existing device status, legacy history or fingerprint jobs.
- Apply migrations after a tested database backup, then start a dedicated queue
  worker **before** enabling the feature:

  ```sh
  php artisan migrate --force
  php artisan queue:work database --queue=network-quality --sleep=2 --tries=1 --timeout=75
  ```

  Use a separate Supervisor program for that worker. Keep existing
  `monitoring,default` and `fingerprint` workers untouched. With the
  existing `retry_after=90`, ensure the new worker's effective timeout stays
  below retry_after; a worker timeout of 75 seconds is sufficient.
- Set `NETWORK_QUALITY_ENABLED=true`, confirm allowlist values, then rebuild
  configuration cache, refresh Filament component cache and restart workers.
  The existing application scheduler must be active (every minute).
- Active CMDB devices with a management IP are automatically eligible for
  ICMP quality checks. DNS/TCP/HTTPS targets are managed in the Filament
  **Target Layanan** resource and disabled by default.
- The resource requires `view_any_device` for reading and `update_device`
  for configuration changes, retaining existing Filament Shield permissions.
- Allowlist CIDRs/domains must be specified by server administrators.
  Keep IPv4 CIDR ranges tight; do not whitelist unrelated public targets or
  dynamically submitted URLs. HTTPS does not follow redirects, but approved
  domains must still be trusted because their DNS records can change.
- The new queue may be disabled independently. To roll back operation set
  `NETWORK_QUALITY_ENABLED=false`, clear config cache and restart workers.
  Do not drop the new tables when reverting code unless data preservation
  has been decided separately.

## Data interpretation

- `status=up/down/error` is a probe result. `error` is not evidence of
  device downtime.
- Quality (`good/degraded/bad/unknown`) is **not** legacy online/offline.
  It is derived from configured thresholds (RTT, loss, RTT variation).
- `rtt_variation_ms` is average absolute difference between consecutive
  observed RTTs; it is **not** one-way or RTP jitter.
- The latency chart aggregates RTT results (not DNS/HTTPS response times).
- DNS checks use the server's configured resolver; HTTPS uses the server
  trust store; TCP checks measure a successful handshake. These originate
  on the monitoring server, **not** from end-user networks.
- The first release does **not** include SNMP port metrics, external probe
  agents, ISP route-specific measurement, Wi-Fi RF quality, incident/root
  cause correlation or SLA reports.
- For a production pilot, verify that the probe host can reach the intended
  management IPs; use dedicated probe hosts in later phases for remote sites.

## Operational checks

- `php artisan schedule:list`: includes monitor-network-quality when enabled.
- Verify that the network-quality queue processes jobs, that measurements
  appear in **Kualitas Jaringan**, and that old ping health remains unchanged.
- Stale/blank results should be investigated as scheduling/collector issues,
  not automatically interpreted as device outages.
- Measurements are pruned daily, default retention 14 days. No aggregation
  storage has been added in this phase, so size queue/DB for the planned scale.
- GitHub Actions validates syntax, PHPUnit tests and frontend build; complete
  staging smoke tests before promotion to production.
