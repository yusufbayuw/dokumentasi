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

## Rincian interaktif pada dashboard

- Pada **Kualitas Jaringan**, klik salah satu kartu statistik:
  **Target Diukur**, **Baik**, **Menurun**, **Buruk**, atau **Belum diketahui**.
- Modal Filament menampilkan tabel yang dapat dicari, diurutkan, difilter
  per unit/metode, dan dipaginasi. Satu baris mewakili pengukuran terbaru
  untuk setiap target dalam jendela monitoring aktif (minimal 15 menit).
  Histori lengkap tetap tersedia di tabel halaman utama.
- Aksi **Rekomendasi** memberi langkah diagnosis indikatif berdasarkan
  hasil pengukuran, tanpa perubahan otomatis pada perangkat.
- Pengguna dengan izin `update_device` dapat menekan **Uji Ulang**
  pada target aktif. Permintaan masuk ke queue `network-quality` dengan
  pembatasan satu permintaan per target per 60 detik. Tabel pada modal
  memperbarui diri setiap 15 detik selama terbuka.
- Aksi **Buka Perangkat** dan **Target Layanan** hanya tampil sesuai
  hak akses dan tipe target. Tidak ada migrasi, cron, atau worker baru.


## Phase 5: multi-location probes and ISP monitoring (opt-in)

The original local monitoring still runs even with remote probes disabled.
Remote reports are accepted only if BOTH NETWORK_QUALITY_ENABLED=true and
NETWORK_QUALITY_REMOTE_ENABLED=true. No new server-side queue or cron is
required; the separate agent is scheduled by cron on each probe host.

### Register and secure each probe

1. Back up DB and run php artisan migrate --force on the central server,
   then clear and rebuild config cache as the same system user as aaPanel.
2. Turn on NETWORK_QUALITY_REMOTE_ENABLED=true in central .env.
3. In Filament **Infrastruktur > Lokasi Probe**, register a name, unit, ISP
   and strong random token (>=32 characters). Keep the location disabled
   until setup is tested. Token is stored only as SHA-256 digest, cannot be
   retrieved from the UI, and must be saved securely by the administrator.
   Example token generation: python3 -c 'import secrets;print(secrets.token_hex(32))'
4. Create explicit service targets in **Target Layanan** and enable them.
   Each location's agent gets a local static list of approved target IDs,
   probe types and exact addresses. The collector never accepts arbitrary
   destinations pushed by the central server.
5. Copy scripts/network_probe_agent.py and the example JSON (edited for
   your location) to a trusted machine on the desired ISP/network path.
   Python >=3.10 is required, with system ping installed on Linux. The
   agent needs egress HTTPS access to the central application URL.
   Keep config outside the public webroot and do NOT store any API token
   inside the JSON file or the Git repository.
6. On that host, set NQ_PROBE_TOKEN via a protected environment file or
   service manager. Run once with token in process environment and a JSON
   file as the --config argument. For production, use a protected
   environment file and a 5-minute cron or systemd timer. Do not enter
   secrets into shell history.
7. Enable the probe location in Filament. Confirm **Terakhir Mengirim**
   changes and results appear in **Kualitas per ISP**.

### How values are defined

- A probe location is a measurement vantage point. ISP names are configured
  by ICT, not automatically discovered. If a host is multi-homed, binding
  traffic to an ISP/network interface is the operator's responsibility.
- Each target/location combination has an independent monitor_key; original
  local monitor keys remain untouched. Local data display as Server Utama
  and remote data as its registered location/ISP.
- The remote agent uses static local CIDR/domain allowlists, HTTPS TLS
  validation and no redirect-following. API reports require a location
  Bearer token, are rate-limited, must be fresh (+/-5min default), must
  match an enabled central target (including address/port/method) and have
  unique UUID report IDs. The server controls ISP labels and quality
  thresholds; the client cannot choose them.
- Results are accepted from a trusted probe credential; this does not
  cryptographically prove they reflect the actual route or ISP. Restrict
  token access, deploy the agent on dedicated systems and rotate/revoke
  tokens if compromised. No arbitrary shell execution is implemented by
  the central server.
- Missing or stale remote reports indicate a probe-health issue, not
  automatically ISP downtime. The **Kualitas per ISP** dashboard compares
  latest samples, not contractual SLA uptime or total ISP bandwidth.
- Rollback: set NETWORK_QUALITY_REMOTE_ENABLED=false, clear config cache,
  then disable remote-agent cron tasks. Leave historical data/tables intact.

### Troubleshooting

- 401: location disabled or token mismatch.
- 404: remote monitoring globally disabled.
- 422: target not enabled/matching, server allowlist mismatch, clock skew,
  or malformed report.
- 429: report rate limit reached; stagger agent tasks.
- 500: inspect central Laravel logs as www, never log or print tokens.
