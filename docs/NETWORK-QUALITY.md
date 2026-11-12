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

## Phase 4: SNMPv3 / SNMPv2c interface telemetry (opt-in)

- All code paths are inactive until **both** NETWORK_QUALITY_ENABLED=true and
  NETWORK_QUALITY_SNMP_ENABLED=true. Adding migration alone does not contact
  network devices. Existing ping, topology, device health, and fingerprint jobs
  remain separate.
- Install and enable the **php82-snmp** extension through aaPanel for the
  exact CLI PHP used by the queue worker (example check:
  /www/server/php/82/bin/php -m | grep -i snmp). If missing, collector writes
  an unknown measurement with snmp_extension_missing instead of marking the
  port down.
- SNMPv3 uses **authPriv** with AES privacy (recommended); supported authentication
  algorithms are SHA256, SHA512 (Net-SNMP version permitting), and SHA for older
  devices. MD5, DES, noAuthNoPriv, SNMPv1 is excluded; SNMPv2c is available separately with explicit opt-in.
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

## Phase 6: incident correlation, observed availability and optional alerts

- This phase is off by default via NETWORK_QUALITY_INCIDENTS_ENABLED=false
  even if the original Network Quality feature is active. Existing ICMP,
  device health, fingerprint and SNMP traffic collection remain unchanged.
- Run additive DB migration after backup and use the existing
  network-quality worker and Laravel Scheduler; no new server-side queue
  worker or Cron entry is necessary.
- When enabled, a five-minute analysis looks at up to 500 recent monitor
  keys (configurable). Default three consecutive down, bad or collector-error
  samples open an incident. Two consecutive good/up samples resolve one.
  Single-sample spikes do not open incidents. Unavailable collector is
  classified separately (not automatically a network outage).
- Filament **Infrastruktur > Insiden Jaringan** supports status/severity
  filters, contextual topology/probe hints, read-only audit history and
  permission-checked acknowledgment/manual resolution. Changes are logged
  in network_incident_events. Manual closure is re-opened if repeated
  poor measurements recur.
- Correlation checks CMDB neighbor connections or independent probe
  monitor keys for the same target; these are hints, not verified root cause.
  No device configuration is changed automatically.
- Hourly aggregation of current and previous day creates
  network_sla_daily. Filament **Laporan Ketersediaan** shows a
  date-filtered table and XLSX bulk export. Observed availability is
  up/(up+down) samples; collector errors are excluded. This is NOT
  elapsed-time uptime, guaranteed service availability, or contract SLA.
- Optional notification emails are OFF by default. To enable, configure
  NETWORK_QUALITY_ALERTS_ENABLED=true and comma-separated recipient
  addresses in NETWORK_QUALITY_ALERT_EMAILS plus the existing Laravel
  mailer. The default queue worker processes notifications. Messages are
  sent on opening, reopening or escalation, not on every poll.
- For rollout, start with alert emails disabled and validate multiple
  consecutive samples before turning on external notifications. Inspect
  the queue failed-jobs report and mail configuration first.
- To roll back, disable NETWORK_QUALITY_INCIDENTS_ENABLED, rebuild
  Laravel configuration cache and restart queue workers. Do not drop
  audit/reporting tables without separately deciding data retention.


## SNMPv2c compatibility (new; optional and security-restricted)

The UI at **Infrastruktur > Perangkat > Edit > Monitoring SNMP** now
offers a per-device **Versi SNMP** choice (v3 or v2c). Existing profiles
automatically remain v3, with their encrypted authPriv credentials
unchanged. Only one protocol/profile is configured per device; when
switching protocols, credentials for the old protocol are cleared from
the database and must be entered again if switching back.

### Security and activation

- SNMPv2c is **not SNMPv2 with encryption**: its community string travels
  unencrypted in network packets. Prefer SNMPv3 authPriv on all devices
  that support it. Use v2c only for legacy hardware on a dedicated
  management VLAN or other trusted and restricted segment.
- Configure the SNMPv2c community on the device itself as **read-only**
  and allow UDP port 161 only from the monitoring server's management
  address. This application issues only read operations (GET/WALK),
  but the device must enforce read-only privileges.
- Community must be unique (8–128 characters, preferably 16+ random),
  never the common public/private defaults. Input is encrypted using the
  existing Laravel APP_KEY, never repopulated into the form, never shown
  in tables or logged. This **does not encrypt traffic on the wire**.
- In .env, turn on SNMP and separately opt in to the weaker protocol:

  NETWORK_QUALITY_ENABLED=true
  NETWORK_QUALITY_SNMP_ENABLED=true
  NETWORK_QUALITY_SNMP_V2C_ENABLED=true

  Setting SNMP_V2C_ENABLED to false leaves existing v3 polling active
  and stops dispatch/collection of v2c measurements. This flag is OFF
  by default even if SNMPv3 monitoring is enabled.
- For aaPanel production using **PHP 8.1**, ensure the same CLI binary is
  used by the queue worker and the extension is enabled:

  /www/server/php/81/bin/php -r 'echo extension_loaded("snmp") ? "OK" : "MISSING";'

  No additional Python package, Cron task or Supervisor program is needed
  for v2c: it uses the existing network-quality queue worker.
- Before pulling the new code in production, back up the database.
  This feature has an **additive migration** that adds version and
  encrypted community columns while allowing the v3 credential columns
  to be null for v2c-only records. Run migration and rebuild cached config
  as **www** before using the new UI. Do not silently change APP_KEY.
- The interface monitor uses the same IF-MIB 64-bit counters for v3
  and v2c. When changing version, the rate requires two new measurements
  from the same protocol; historical data remains and is labeled with
  its SNMP version in the Traffic Interface/History tables.
- Rollback operationally: set NETWORK_QUALITY_SNMP_V2C_ENABLED=false,
  rebuild config cache and restart workers; v3 remains available.
  Schema rollback is intentionally blocked if v2c profiles exist,
  to avoid silently destroying their credentials.

### Filament workflow

1. Open **Infrastruktur > Perangkat**, then edit a switch/router.
2. In the **Monitoring SNMP** relation tab, click **Atur SNMP** or edit
   the existing profile; choose SNMPv3 (recommended) or SNMPv2c.
3. For v2c, enter the unique community string in its password field.
   The v3 authentication and privacy password inputs are hidden in v2c mode.
4. Toggle **Aktifkan Polling SNMP**, ensure the device itself has
   Monitoring enabled and a permitted management IP.
5. Check **Infrastruktur > Traffic Interface**; v2c rows are labeled
   separately and include port status, RX/TX, error deltas and history.


## Data SNMP Per Perangkat - raw snapshot capture

SNMP credentials and data belong to each individual CMDB device. The primary
collector now gathers accessible SNMP OIDs from that device and saves historical
snapshots. Port/interface traffic processing is not required and is not
auto-generated from the raw data. The old Traffic Interface view is retained
as historical reference; its legacy polling job is OFF by default.

### How it works

- Configure SNMPv3 (preferred) or SNMPv2c (read-only community) in
  Infrastruktur > Perangkat > Edit > Monitoring SNMP. Keep Monitoring
  Perangkat and Aktifkan Polling SNMP enabled. Ensure its management IPv4
  address is allowed by NETWORK_QUALITY_ALLOWED_CIDRS.
- Open Infrastruktur > Data SNMP Perangkat to see each configured device,
  snapshot status, OID count, time and diagnostic. Use Ambil Semua OID (or
  Ambil Data SNMP in the device tab) to request a manual collection.
  Lihat OID browses raw OID, type, encoding and values using a searchable
  paginated native Filament table. Riwayat Snapshot opens older captures.
- The scheduler collects a snapshot every 60 minutes by default via the
  existing network-quality queue, without needing interface CMDB records.
  Each continuation job issues a bounded number of read-only SNMP GETNEXT.
- The default root is 1.3.6.1, encompassing standard Internet and vendor
  MIB branches, insofar as allowed by the remote device's SNMP view.
- Values are stored with their numeric OID, SNMP type number, raw content,
  encoding, byte size and truncated marker. Storage is encrypted with the
  existing Laravel APP_KEY. Neither OID data nor credentials are written to
  public files, CLI arguments or logs.
- Viewing raw OID data requires BOTH view_any_device and update_device
  privileges. Snapshot collection does not mutate device configuration or
  create/change CMDB interfaces.
- Snapshot status:
  * running: capture is ongoing
  * complete: all accessible OIDs under configured roots traversed safely
  * partial: OID/size/time bounds or polling problem interrupted collection
  * failed: no usable data obtained
  'Complete' does not imply manufacturer-hidden, unauthorised or non-SNMP
  device properties have been retrieved.

### Production rollout - aaPanel with PHP 8.1

1. Back up database. Deploy the code and run additive migrations (profile
   diagnostic fields and new snapshot/OID tables) as www using the PHP 8.1
   CLI, then rebuild config cache and restart network-quality worker.
2. Confirm the same worker binary has PHP SNMP:
   /www/server/php/81/bin/php -m | grep -i snmp
3. Use these conservative starting values:

   NETWORK_QUALITY_ENABLED=true
   NETWORK_QUALITY_SNMP_ENABLED=true
   NETWORK_QUALITY_SNMP_V2C_ENABLED=true
   NETWORK_QUALITY_SNMP_SNAPSHOT_INTERVAL_MINUTES=60
   NETWORK_QUALITY_SNMP_SNAPSHOT_ROOTS=1.3.6.1
   NETWORK_QUALITY_SNMP_SNAPSHOT_MAX_OIDS=20000
   NETWORK_QUALITY_SNMP_SNAPSHOT_CHUNK_OIDS=30
   NETWORK_QUALITY_SNMP_SNAPSHOT_CHUNK_SECONDS=15
   NETWORK_QUALITY_SNMP_SNAPSHOT_MAX_MINUTES=90
   NETWORK_QUALITY_SNMP_SNAPSHOT_MAX_VALUE_BYTES=8192
   NETWORK_QUALITY_SNMP_SNAPSHOT_RETENTION_DAYS=3
   NETWORK_QUALITY_SNMP_LEGACY_INTERFACE_METRICS=false

4. Run one capture against a single registered device and check its status
   and OID count before enabling large batches.

### Safety and limits

SNMP may expose routing tables, ARP/MAC mappings, usernames and other
sensitive device information. Restrict raw viewing to trusted operators,
use a read-only remote SNMP view and a management VLAN. SNMPv2c community
text is not encrypted on the wire.

The default maximum is 20,000 OIDs and 8,192 bytes for each value, at
most 90 minutes per snapshot, with three days of retention. A partial
snapshot EXPLICITLY means the available capture was incomplete.
Larger limits are configurable but may increase network/device load,
database storage, queue processing time and retention costs. Old
snapshots and their encrypted OIDs are deleted together by retention.
To stop collection, set NETWORK_QUALITY_SNMP_ENABLED=false, refresh
config cache and restart the worker.
