# Network Quality Monitoring (Fase 0-4)

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

## Phase 4: SNMPv3 interface telemetry (opt-in)

- All code paths are inactive until **both** NETWORK_QUALITY_ENABLED=true and
  NETWORK_QUALITY_SNMP_ENABLED=true. Adding migration alone does not contact
  network devices. Existing ping, topology, device health, and fingerprint jobs
  remain separate.
- Install and enable the **php82-snmp** extension through aaPanel for the
  exact CLI PHP used by the queue worker (example check:
  /www/server/php/82/bin/php -m | grep -i snmp). If missing, collector writes
  an unknown measurement with snmp_extension_missing instead of marking the
  port down.
- SNMP read access is **v3 authPriv only**, AES privacy; supported authentication
  algorithms are SHA256, SHA512 (Net-SNMP version permitting), and SHA for older
  devices. MD5, DES, noAuthNoPriv, SNMPv1/v2c are deliberately excluded.
- Grant a read-only SNMPv3 user on each switch/router with UDP 161 restricted
  to the monitoring server. In **Perangkat > edit > Monitoring SNMPv3**,
  configure a unique profile. Secrets are encrypted via Laravel APP_KEY and
  never populated into edit forms. Do not rotate APP_KEY without planning
  re-encryption of existing credentials.
- Existing CMDB interfaces are matched automatically to IF-MIB ifName **only
  when a unique exact match exists**. If the model uses nonmatching naming,
  enter the numeric SNMP ifIndex in the existing Interface Jaringan tab.
  No new CMDB devices/interfaces are created by SNMP; no topology is modified.
- OIDs queried: ifHCInOctets, ifHCOutOctets (64-bit), ifInErrors, ifOutErrors,
  ifOperStatus, ifHighSpeed. Traffic rates require **two consecutive successful
  samples** and represent approximate measured Mbps at the interface, not a
  guaranteed ISP or end-to-end application speed. Error counts are deltas for
  the sampling window. Counter reset/wrap, invalid result, or unexpectedly
  high rates are *not* interpreted as valid throughput.
- Open **Infrastruktur > Traffic Interface** to inspect current RX/TX, port
  status, error deltas and 24h history; the page uses native Filament tables
  and charts. Privileged operators can queue an on-demand sample. Only the
  existing network-quality queue worker is required, but SNMP polls share its
  capacity with quality probes; begin with a small device cap.
- Example .env values for a cautious pilot:

  NETWORK_QUALITY_SNMP_ENABLED=true
  NETWORK_QUALITY_SNMP_INTERVAL_MINUTES=5
  NETWORK_QUALITY_SNMP_MAX_DEVICES=10
  NETWORK_QUALITY_SNMP_MAX_INTERFACES=16

- Back up DB, run php artisan migrate --force, rebuild configuration cache
  **as the www user**, then restart queue workers. Verify the PHP CLI
  extension and SNMP read permissions before expanding scope.
- Retention of SNMP samples follows network-quality retention days (min 7).
- Roll back operations by setting NETWORK_QUALITY_SNMP_ENABLED=false, clearing
  config cache, and restarting workers. Do not drop tables automatically.
