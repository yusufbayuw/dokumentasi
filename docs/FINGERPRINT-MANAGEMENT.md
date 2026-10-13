# Manajemen Fingerprint (rats/zkteco)

Halaman **Perangkat → Kelola Fingerprint** muncul hanya jika nama jenis perangkat mengandung `finger`. Tombol operasional tersedia di dalam section **Aksi Fingerprint** pada isi halaman, bukan hanya pada toolbar header atau action row. Perangkat lain, relasi CMDB, dan alur reboot existing tidak diubah.

## Fitur

- **Baca Info Mesin**: nama, serial, platform, firmware, versi, OS, PIN width dan jam mesin (snapshot terakhir).
- **Sinkron Pengguna**: ID, UID, nama dan role, tanpa menyimpan kata sandi, nomor kartu atau template biometrik.
- **Tarik Log Absensi**: seluruh log dibaca dari mesin, dideduplikasi menggunakan hash per perangkat, disimpan tanpa pernah menghapus log pada perangkat; pencarian dan ekspor CSV.
- **Samakan Jam**: perintah tulis yang membutuhkan konfirmasi; jam disetel dari timezone Laravel. Pastikan `APP_TIMEZONE`/timezone config aplikasi benar.
- **Reboot**: action di baris perangkat dan tombol pada halaman Kelola Fingerprint terlihat bagi role `super_admin` atau pengguna dengan permission `update_device`. Penolakan akses dicek ulang saat action dipanggil.
- **Riwayat Operasi**: status, jumlah rekaman hasil baca, kegagalan.

Operasi perangkat berjalan via queue `device-actions`, bukan saat render halaman. Sync manual saja: tidak ada cron baru, tidak ada polling otomatis, tidak menghapus rekaman mesin. Tab Pengguna/Absensi dan ekspor hanya untuk `super_admin` atau permission `view_fingerprint_data`. Sinkronisasi serta kontrol memerlukan permission `update_device` atau `super_admin`. Perubahan jam mesin juga memerlukan `manage_fingerprint_clock` (atau role `super_admin`). Permission khusus dibuat saat migrasi tanpa otomatis diberikan kepada operator.

## Deploy

1. Backup database, lalu `php artisan migrate --force`.
2. `php artisan filament:optimize-clear` lalu `php artisan optimize:clear`.
3. Pastikan ekstensi PHP **sockets** aktif dan UDP port **4370** dapat dijangkau dari server Laravel menuju mesin.
4. Jalankan worker `php artisan queue:work --queue=device-actions,monitoring,default --sleep=2 --tries=1 --timeout=200`; untuk supervisor pilih timeout process yang tidak lebih kecil dari timeout job (180 detik), lalu `php artisan queue:restart`.
5. Login sebagai super_admin dan uji **Baca Info Mesin** pada satu mesin uji. Periksa tab Riwayat Operasi sebelum menarik pengguna/absensi.
6. Waktu log disimpan sebagaimana di mesin, belum dikonversi ke zona waktu lain. Pastikan jam/timezone mesin sebelum ekspor.
7. Beri akses `view_fingerprint_data` hanya kepada petugas yang berhak mengakses data kehadiran. Audit perizinan jika menambah role baru.

## Pembatasan dan mitigasi

- Library `rats/zkteco` menggunakan **UDP**, timeout socket bawaan relatif lama (60 detik), dan kompatibilitas bergantung firmware.
- Parser pengguna dari paket mengembalikan password mesin; hasil diproyeksikan ke whitelist dan data password langsung dibuang sebelum penyimpanan.
- `getUser()` / `getAttendance()` bisa mengembalikan array kosong bila firmware tidak membalas dengan data. Respons kosong **tidak memicu penghapusan lokal**. Riwayat tetap mencatat jumlah 0.
- Event absensi yang identik dideduplikasi, tetapi event yang benar-benar memiliki seluruh field identik juga dianggap satu peristiwa. Tidak ada transfer template sidik jari.
- Operasi berisiko seperti hapus pengguna, hapus semua log, shutdown, reset dan penulisan biometrik tidak ditampilkan.
- Jika perangkat memakai password komunikasi tambahan/ADMS/TCP-only, paket existing belum tentu cocok; pengujian langsung perangkat tetap diperlukan.
