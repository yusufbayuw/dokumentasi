# Reboot All Fingerprint

Aksi **Reboot All** berada di header halaman **Perangkat** (bukan aksi tiap baris).
Hanya `super_admin` yang dapat melihat dan menjalankannya.

## Perilaku

1. Modal menunjukkan jumlah mesin berjenis fingerprint, jumlah kandidat dengan alamat IPv4 management yang valid, dan jumlah yang dilewati.
2. Administrator harus mengetik persis `REBOOT ALL` lalu mengonfirmasi.
3. Sistem meninjau ulang kandidat saat submit, mencatat permintaan ke activity log, dan mengantrikan job `RestartFingerprintDevice` existing satu per mesin.
4. Job diberi jarak jadwal **120 detik**, sehingga tidak semuanya dirilis ke worker pada saat yang sama. Queue yang sibuk/memiliki beberapa worker dapat memengaruhi waktu eksekusi sebenarnya; jeda adalah pengaturan waktu pelepasan job, bukan jaminan urutan eksekusi yang ketat.
5. Target adalah **semua** fingerprint pada database, **tidak mengikuti filter/tab** pada halaman perangkat, dan tidak perlu mengaktifkan opsi reboot terjadwal (`auto_reboot_enabled`).
6. Fingerprint dengan IPv4 management kosong atau tidak valid dilewati; perangkat lain tidak disentuh. Job existing memeriksa kembali tipe perangkat dan IP ketika dieksekusi.
7. Permintaan serupa diblokir selama seluruh jendela penjadwalan dan 10 menit tambahan untuk mengurangi risiko reboot ganda. Cache store Laravel harus writable dan konsisten antar worker.

## Prasyarat server

- `QUEUE_CONNECTION` harus asinkron, misalnya `database` atau `redis` (aksi akan menolak konfigurasi `sync`/`null`).
- Worker supervisor harus memproses queue `device-actions`. Contoh: `php artisan queue:work --queue=device-actions,default --sleep=3 --tries=2 --timeout=90`.
- `storage/framework/cache` harus dapat ditulis oleh PHP-FPM/queue worker (`www` pada aaPanel).
- Paket `rats/zkteco`, ekstensi PHP sockets, dan jaringan UDP/4370 tetap dibutuhkan untuk eksekusi reboot. Status "berhasil dijadwalkan" **bukan** bukti mesin sudah reboot; periksa log queue dan activity log.
- Gunakan di luar jam sibuk; perintah ini dapat memutus layanan absensi untuk banyak mesin.
- Perubahan ini **tidak** menghapus absensi, tidak menyentuh basis data fingerprint di perangkat, serta tidak mengubah mekanisme reboot terjadwal existing.

## Pengujian

Tes unit meliputi identifikasi fingerprint, validitas IPv4, otorisasi hanya super admin, dan jeda penjadwalan. Tes fitur memastikan aksi `rebootAllFingerprint` terdaftar pada `ListDevices::getHeaderActions()`. Pengujian langsung ke mesin fisik tetap perlu di server.
