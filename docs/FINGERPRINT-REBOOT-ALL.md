# Reboot All Fingerprint

Aksi **Reboot All** berada di header halaman **Perangkat** (bukan aksi tiap baris).
Hanya `super_admin` yang dapat melihat dan menjalankannya.

## Perilaku

1. Modal menunjukkan jumlah mesin berjenis fingerprint, jumlah kandidat dengan alamat IPv4 management yang valid, dan jumlah yang dilewati.
2. Administrator harus mengetik persis `REBOOT ALL` lalu mengonfirmasi.
3. Sistem meninjau ulang kandidat saat submit, mencatat permintaan ke activity log, dan mengantrikan job `RestartFingerprintDevice` existing satu per mesin.
4. Job diberi jarak jadwal **2 detik**, dari antrean. Eksekusi/pengiriman UDP sebenarnya bisa lebih lambat akibat proses worker atau perangkat; gunakan satu worker fingerprint agar perintah tidak dieksekusi secara paralel.
5. Target adalah **semua** fingerprint pada database, **tidak mengikuti filter/tab** pada halaman perangkat, dan tidak perlu mengaktifkan opsi reboot terjadwal (`auto_reboot_enabled`).
6. Fingerprint dengan IPv4 management kosong atau tidak valid dilewati; perangkat lain tidak disentuh. Job existing memeriksa kembali tipe perangkat dan IP ketika dieksekusi.
7. Permintaan serupa diblokir selama seluruh jendela penjadwalan dan 10 menit tambahan untuk mengurangi risiko reboot ganda. Cache store Laravel harus writable dan konsisten antar worker. Job lama pada queue `device-actions` tidak otomatis dipindahkan; drain via worker lama sebelum menghentikannya.

## Prasyarat server

- Koneksi khusus `fingerprint` pada `config/queue.php` memakai driver database dan tabel `jobs` existing, dengan `retry_after=240`; queue umum tetap memakai konfigurasi `QUEUE_CONNECTION` existing.
- Worker Supervisor terpisah harus menjalankan koneksi queue `fingerprint`, misalnya `php artisan queue:work fingerprint --queue=fingerprint --sleep=2 --tries=1 --timeout=200` (1 process). Worker umum menangani `monitoring,default` di koneksi database existing.
- `storage/framework/cache` harus dapat ditulis oleh PHP-FPM/queue worker (`www` pada aaPanel).
- Paket `rats/zkteco`, ekstensi PHP sockets, dan jaringan UDP/4370 tetap dibutuhkan untuk eksekusi reboot. Status "berhasil dijadwalkan" **bukan** bukti mesin sudah reboot; periksa log queue dan activity log.
- Gunakan di luar jam sibuk; perintah ini dapat memutus layanan absensi untuk banyak mesin.
- Perubahan ini **tidak** menghapus absensi, tidak menyentuh basis data fingerprint di perangkat, serta tidak mengubah mekanisme reboot terjadwal existing.

## Pemisahan operasional

- Queue `fingerprint`: seluruh kontrol mesin (reboot manual/massal/terjadwal, baca info, sinkron pengguna/absensi/jam). Gunakan 1 process Supervisor khusus agar operasi serial.
- Queue `monitoring` dan `default`: ping dan pekerjaan umum lainnya. Kedua worker tidak perlu memproses queue fingerprint.
- Koneksi fingerprint memakai `retry_after=240` agar lebih panjang dari worker timeout 200 detik serta job sync timeout 180 detik. Tidak mengubah `retry_after` monitoring/default.
- Setelah deployment, jalankan `php artisan optimize:clear` dan `php artisan queue:restart` sebagai user web server, dan pastikan Supervisor menjalankan kembali kedua worker.
- Jeda 2 detik adalah selang *penjadwalan* perintah, bukan kepastian mesin mulai restart tiap 2 detik.

## Pengujian

Tes unit meliputi identifikasi fingerprint, validitas IPv4, otorisasi hanya super admin, dan jeda penjadwalan. Tes fitur memastikan aksi `rebootAllFingerprint` terdaftar pada `ListDevices::getHeaderActions()`. Pengujian langsung ke mesin fisik tetap perlu di server.
