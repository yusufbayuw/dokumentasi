# Dokumentasi ICT YTB

Sistem internal untuk dokumentasi dan pemantauan infrastruktur ICT Yayasan Taruna Bakti. Aplikasi dibangun dengan Laravel 10 dan Filament 3.

## Ruang Lingkup

Data utama yang dikelola:

- Perangkat ICT (`devices`)
- Tipe perangkat (`type_devices`)
- Jaringan (`networks`)
- Pool IP (`ip_addresses`)
- Gedung (`gedungs`)
- Lantai (`lantais`)
- Ruangan (`ruangans`)
- Unit (`units`)
- Topologi perangkat (`topologies`)
- Pengguna, role, dan permission melalui Filament Shield

Relasi utama:

```text
Gedung -> Lantai -> Ruangan -> Device -> IP Address -> Network
                              |
                              -> Topology
```

`devices.ip_address_id` adalah sumber kebenaran utama untuk menentukan apakah sebuah IP sedang digunakan perangkat. Kolom `ip_addresses.booked` tetap disinkronkan untuk kompatibilitas UI.

## Fitur Operasional

- Inventarisasi perangkat dan lokasi fisik
- Pengelolaan subnet dan pool IP
- Status online perangkat melalui ping
- Topologi perangkat
- Denah lantai dan posisi perangkat
- Export data
- PDF laporan dokumentasi ICT
- Reboot perangkat fingerprint dari aplikasi
- Reboot fingerprint terjadwal pukul 03:00
- Role dan permission melalui Filament Shield

## Persyaratan

- PHP 8.1 atau lebih baru
- MySQL / MariaDB
- Composer
- Node.js dan NPM untuk build asset
- Perintah `ping` tersedia pada server
- Scheduler Laravel aktif
- Queue worker aktif apabila `QUEUE_CONNECTION` menggunakan driver asynchronous

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

Konfigurasikan `.env`, terutama database, APP_URL, cache, session, queue, dan mail sesuai environment.

## Operasional Production

### Scheduler

Laravel scheduler wajib dijalankan setiap menit:

```cron
* * * * * cd /path/to/dokumentasi && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler saat ini menjalankan reboot perangkat bertipe `Finger Print` setiap pukul 03:00. Proses sudah memakai `withoutOverlapping()` dan kegagalan satu perangkat tidak menghentikan perangkat lainnya.

### Queue

Jika menggunakan Redis/database queue:

```bash
php artisan queue:work --tries=3 --timeout=60
```

Gunakan process manager seperti Supervisor atau systemd agar worker otomatis hidup kembali.

Setelah deployment kode baru:

```bash
php artisan queue:restart
```

### Cache Production

```bash
php artisan optimize
```

Setelah perubahan konfigurasi atau deployment besar, bila diperlukan:

```bash
php artisan optimize:clear
php artisan optimize
```

## Backup

Sebelum perubahan schema, upgrade framework, atau perubahan besar pada jaringan:

1. Backup database.
2. Pastikan backup dapat direstore.
3. Backup file upload pada storage/public yang digunakan aplikasi.
4. Lakukan perubahan pada periode maintenance apabila menyentuh konfigurasi Network/IP.

Database adalah sumber utama dokumentasi infrastruktur, sehingga backup harus diperlakukan sebagai bagian dari operasi rutin aplikasi.

## Aturan Keamanan Data

- Jangan menyimpan secret baru secara plaintext.
- Kredensial perangkat tidak boleh masuk export, PDF, atau log.
- Aksi reboot harus hanya diberikan kepada pengguna yang memiliki permission update perangkat.
- Route laporan membutuhkan permission `view_any_device`.
- Ping hanya menerima IPv4 valid dan dijalankan tanpa shell command concatenation.
- Tipe perangkat yang masih digunakan tidak dapat dihapus dari UI.
- Network yang masih memiliki IP yang dipakai Device tidak dapat dihapus dari UI.

## Pengelolaan Network dan IP

Network dikelola melalui menu Jaringan. CIDR yang diterima UI dibatasi `/16` sampai `/30` agar aplikasi tidak membuat pool IP yang terlalu besar.

Saat Network berubah, sistem:

1. Menghitung rentang host baru.
2. Menolak perubahan jika ada IP aktif yang akan keluar dari rentang baru.
3. Mempertahankan record IP yang masih valid.
4. Menambahkan IP baru yang dibutuhkan.
5. Menghapus IP lama hanya jika tidak direferensikan Device.

Jangan melakukan manipulasi massal langsung pada tabel `ip_addresses` tanpa backup dan pengecekan relasi Device.

## Monitoring IP

Aksi **Ping Semua IP** mengambil target dari `devices.ip_address_id`, bukan hanya dari flag `booked`.

Setiap ping dijalankan sebagai process dengan argumen terpisah dan timeout terbatas. Input yang bukan IPv4 valid akan ditolak dan dicatat ke log.

## Laporan

Laporan tersedia pada route `/laporan` untuk pengguna yang memiliki permission `view_any_device`.

Data laporan dimuat melalui eager loading agar tidak menghasilkan query berulang. Perangkat yang belum mempunyai IP, ruangan, lantai, gedung, atau unit tetap dapat muncul tanpa menggagalkan proses PDF.

## Quality Guard

GitHub Actions menjalankan PHP syntax lint setiap push ke `main` melalui:

```text
.github/workflows/php-lint.yml
```

Regression test aplikasi masih perlu dikembangkan lebih lanjut, terutama untuk:

- perubahan range Network
- assignment dan perpindahan IP Device
- proteksi delete master data
- permission aksi operasional
- generate laporan dengan relasi yang tidak lengkap

## Catatan Upgrade

Aplikasi masih menggunakan Laravel 10 dan Filament 3. Upgrade framework sebaiknya dilakukan setelah backup, regression test inti, dan dependency compatibility audit selesai. Hindari big-bang upgrade langsung pada production.
