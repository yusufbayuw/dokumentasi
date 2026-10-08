# Dokumentasi ICT YTB

Sistem internal **ICT CMDB + IPAM + Network Topology + Infrastructure Monitoring + Asset Lifecycle + Maintenance Operations** berbasis Laravel 10 dan Filament 3.

## Model Data Utama

```text
Unit / Gedung / Lantai / Ruangan
                │
                ▼
              Device
        ┌───────┼────────┐
        ▼       ▼        ▼
 Interface   Lifecycle   Maintenance
    │                     │
    ▼                     ▼
IP Address            History/Audit
    │
    ▼
 Network

DeviceInterface ───── DeviceConnection ───── DeviceInterface
```

`device_interfaces` + `device_connections` adalah arah canonical model jaringan. `devices.ip_address_id` dan `topologies` tetap dipertahankan sementara sebagai compatibility layer untuk data existing.

## Fitur Operasional

- CMDB perangkat, owner, lifecycle, criticality, serial, vendor, garansi, dan lokasi fisik
- IPAM berbasis subnet dengan pool IP otomatis
- Interface jaringan terstruktur dan koneksi port-aware
- Diagram jaringan
- Monitoring ICMP terjadwal dengan status `unknown`, `online`, `offline`, `stale`, dan `disabled`
- Riwayat maintenance dan perpindahan perangkat
- Kualitas data CMDB + audit CLI
- Activity log perubahan data dan aksi perangkat
- Export dan PDF laporan
- Reboot fingerprint manual/terjadwal melalui queue
- Role dan permission melalui Filament Shield

## Persyaratan Production

- PHP 8.1+
- MySQL / MariaDB
- Composer
- Node.js + NPM
- Perintah `ping`
- Laravel scheduler aktif
- Queue worker aktif
- `APP_KEY` stabil dan dibackup karena credential store menggunakan enkripsi aplikasi

## Instalasi

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

## Deployment Setelah Hardening CMDB

Sebelum migrate: backup database, verifikasi restore, simpan `APP_KEY` production dengan aman, dan gunakan maintenance window bila perubahan menyentuh network/IP.

```bash
composer dump-autoload -o
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan migrate --force
php artisan dokumentasi:audit --details
php artisan dokumentasi:migrate-device-credentials --dry-run
php artisan dokumentasi:migrate-device-credentials
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
```

> Setiap deployment yang menambah atau mengubah Filament Resource, Page, Widget, atau Relation Manager wajib membersihkan cache komponen Filament sebelum cache aplikasi dibangun ulang. Jika tidak, Livewire dapat melempar `ComponentNotFoundException` meskipun file PHP sudah ada di server.

Command credential migration memindahkan `devices.username/password` legacy ke `device_credentials` terenkripsi, lalu mengosongkan plaintext. Jangan menjalankannya jika `APP_KEY` belum final/stabil.

## Scheduler dan Queue

Scheduler server wajib berjalan setiap menit:

```cron
* * * * * cd /path/to/dokumentasi && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler aplikasi dispatch monitoring tiap 5 menit dan hanya me-reboot fingerprint dengan `auto_reboot_enabled = true` pada pukul 03:00. Tidak ada lagi reboot otomatis global.

Default `.env.example` menggunakan database queue. Contoh worker:

```bash
php artisan queue:work --queue=device-actions,monitoring,default --sleep=2 --tries=3 --timeout=60
```

## Monitoring

```dotenv
CMDB_MONITORING_STALE_MINUTES=15
CMDB_OFFLINE_AFTER_FAILURES=3
CMDB_MONITORING_SCHEDULE_MINUTES=5
CMDB_MONITORING_HISTORY_RETENTION_DAYS=90
```

Health: `unknown` = belum cukup data; `online` = check terbaru sukses; `offline` = gagal berturut-turut sampai threshold; `stale` = check melewati batas waktu; `disabled` = monitoring dimatikan. Riwayat monitoring lama dipangkas otomatis sesuai retention (default 90 hari).

Management IP dapat berasal dari `devices.ip_address_id` (legacy) atau `DeviceInterface` yang ditandai sebagai Interface Management.

## IPAM dan Network

Subnet adalah sumber pembentuk pool IP. Resource IP tidak menerima create/edit alamat bebas. Network menghitung derived fields di domain layer, menolak subnet overlap, menolak resize yang mengeluarkan IP aktif, serta menjaga assignment pada Device dan DeviceInterface.

## Topology

`DeviceConnection` adalah model graph canonical yang dituju. `Topology` tree lama tetap dipertahankan sementara sebagai compatibility layer dan disinkronkan selama masa transisi.

## Kualitas Data

Dashboard **Kualitas Data** dan command audit memakai rule engine yang sama:

```bash
php artisan dokumentasi:audit
php artisan dokumentasi:audit --details
php artisan dokumentasi:audit --json
```

Pemeriksaan meliputi konflik IP lintas device/interface, booked mismatch, IP di luar subnet, subnet overlap, connection/interface mismatch, self-loop, credential plaintext legacy, monitoring stale/belum berjalan/tanpa management IP, critical device tanpa monitoring, lifecycle/monitoring mismatch, kelengkapan owner/lokasi/asset/serial/interface, MAC invalid, dan maintenance overdue.

## Keamanan dan Lifecycle

- akun dapat dinonaktifkan melalui `users.is_active`; user nonaktif ditolak panel;
- 2FA Breezy tersedia secara opt-in dari profil pengguna; aktifkan untuk admin/operator sensitif sebelum enforcement penuh;
- login terakhir dan waktu perubahan password dicatat;
- credential perangkat baru disimpan terenkripsi dan tidak boleh masuk export/PDF/log;
- device dan maintenance history tidak disediakan aksi delete operasional;
- destructive cascade pada TypeDevice, Network, Gedung, dan Lantai dibatasi;
- reboot perangkat diproses melalui queue dan tetap membutuhkan permission update device;
- route laporan membutuhkan `view_any_device`.

## Backup

Backup operasional harus mencakup database, `storage/public`, `APP_KEY`, dan konfigurasi deployment/Supervisor. Lakukan restore drill berkala; backup yang tidak pernah diuji restore belum dapat dianggap backup operasional.

## Quality Guard / CI

GitHub Actions menjalankan PHP syntax lint, Composer validation/install, test suite, dan frontend build. Regression test harus terus diperluas sebelum framework upgrade besar.

## Upgrade Framework

Laravel 10 + Filament 3 dipertahankan pada hardening ini agar perubahan domain tidak bercampur dengan breaking framework upgrade. Setelah regression suite stabil, lakukan dependency compatibility audit dan upgrade dalam PR terpisah.


## Network Access Control (NAC) — Fase 1

Fondasi NAC WPA3-Enterprise telah ditambahkan sebagai modul **Network Access Control** pada Filament. Model \`NacClient\` untuk endpoint pengguna dipisah dari \`Device\` (inventaris infrastruktur), dengan relasi opsional ke CMDB. Pengelolaan hanya tersedia bagi role \`super_admin\`; penghapusan record NAC diblokir di UI agar jejak audit tidak hilang.

- **Wi-Fi Networks**: SSID dan status enabled, awalnya nonaktif.
- **Access Policies**: assignment VLAN berdasarkan role Spatie, prioritas tertinggi menang; awalnya nonaktif.
- **Network Clients**: pengguna, perangkat, status \`pending|active|revoked\`.
- **Certificates**: metadata issuer + serial + validitas + revocation; private key **tidak** disimpan.
- **Authentication Logs**: setiap keputusan otorisasi di database, dengan pembersihan terjadwal.

API otorisasi privat: \`POST /api/nac/radius/authorize\`, header \`X-NAC-Radius-Key\`, dan allowlist IP opsional. Kebijakan **fail closed** jika tidak ada jaringan, sertifikat, pengguna, perangkat, atau policy yang valid.

**Ini baru fondasi aplikasi**: FreeRADIUS, EAP-TLS, PKI, UniFi, MikroTik, SSO/OIDC external, dan onboarding otomatis **belum** dikonfigurasi. Jangan mengaktifkan SSID produksi sebelum integrasi dan pengujian sertifikat selesai. Rincian kontrak API dan langkah implementasi: [docs/NAC-WPA3.md](docs/NAC-WPA3.md).
