# Manajemen Fingerprint (rats/zkteco)

Di **Infrastruktur → Perangkat**, aksi baris **Kelola Fingerprint** membuka **modal native Filament**, bukan halaman dengan route tersendiri. Aksi hanya muncul untuk perangkat yang nama jenisnya mengandung `finger` dan pengguna yang memiliki hak melihat perangkat. Modal memuat tab Ringkasan, Pengguna, Absensi, dan Riwayat Operasi; operator menjalankan perintah melalui form di bagian bawah modal. Data pengguna/absensi dan ekspor tetap dibatasi izinnya. Route lama `/app/devices/{record}/fingerprint` tidak dipakai lagi; tombol tidak melakukan navigasi.

## Fitur

- **Baca Info Mesin**: nama, serial, platform, firmware, versi, OS, PIN width dan jam mesin (snapshot terakhir).
- **Sinkron Pengguna**: membaca ID, UID, nama dan role, tanpa menulis/menghapus pengguna di mesin dan tanpa menyimpan kata sandi, nomor kartu atau template biometrik. Modal menampilkan maksimal 100 pengguna tersimpan dengan pencarian pada baris yang ditampilkan dan menyediakan tombol **Unduh seluruh XLSX** berisi semua pengguna tersimpan (ID, UID, nama, role, terakhir terbaca). Ekspor memaksa kolom teks menjadi teks literal agar ID berawalan nol tidak berubah dan data tidak dieksekusi sebagai formula.
- **Tarik Log Absensi**: seluruh log dibaca dari mesin, dideduplikasi menggunakan hash per perangkat, disimpan tanpa menghapus log pada perangkat. Modal menampilkan 100 event terbaru yang dapat dicari dan menyediakan ekspor CSV seluruh event melalui aksi Livewire yang memeriksa izin kembali.
- **Samakan Jam**: perintah tulis yang membutuhkan checkbox konfirmasi eksplisit; jam disetel dari timezone Laravel. Pastikan timezone aplikasi benar.
- **Reboot**: tetap tersedia sebagai aksi baris terpisah yang memerlukan konfirmasi, dan sebagai opsi operasi dalam modal (juga memerlukan checkbox konfirmasi). Hanya untuk `super_admin` atau pengguna dengan permission `update_device`; izin divalidasi kembali saat aksi dijalankan.
- **Riwayat Operasi**: status, jumlah rekaman hasil baca, kegagalan.

Operasi perangkat berjalan via queue khusus `fingerprint` pada koneksi database tersendiri, bukan saat render halaman. Sync manual saja: tidak ada cron baru dan tidak ada polling mesin otomatis; data dari database diperbarui setiap 10 detik **selama modal terbuka**. Setelah operator mengantrekan perintah, modal tetap terbuka dan riwayat diperbarui tanpa perlu menutupnya. Tidak menghapus rekaman mesin. Tab Pengguna/Absensi dan ekspor hanya untuk `super_admin` atau permission `view_fingerprint_data`. Sinkronisasi serta kontrol memerlukan permission `update_device` atau `super_admin`. Perubahan jam mesin juga memerlukan `manage_fingerprint_clock` (atau role `super_admin`). Permission khusus dibuat saat migrasi tanpa otomatis diberikan kepada operator.

## Deploy

1. Untuk instalasi pertama fitur fingerprint, backup database dan jalankan `php artisan migrate --force` (migrasi fitur sudah ada sebelumnya). **Perubahan UI modal ini sendiri tidak memerlukan migrasi baru.**
2. `php artisan filament:clear-cached-components` lalu `php artisan optimize:clear`.
3. Pastikan ekstensi PHP **sockets** aktif dan UDP port **4370** dapat dijangkau dari server Laravel menuju mesin.
4. Jalankan worker Supervisor khusus fingerprint dengan koneksi `fingerprint` dan 1 process: `php artisan queue:work fingerprint --queue=fingerprint --sleep=2 --tries=1 --timeout=200`. Koneksi ini memiliki `retry_after=240` untuk menangani job sinkronisasi hingga 180 detik. Worker ping berjalan terpisah di koneksi database dengan `--queue=monitoring,default`. Setelah perubahan, jalankan `php artisan queue:restart`. Job fingerprint lama yang sudah berada pada queue `device-actions` harus dituntaskan menggunakan worker lama sebelum dinonaktifkan.
5. Login sebagai super_admin, masuk ke daftar Perangkat, lalu klik **Kelola Fingerprint** pada mesin uji. Pilih **Baca Info Mesin**, antrikan, lalu lihat tab Riwayat Operasi tanpa menutup modal. Setelah sinkronisasi pengguna selesai, buka tab Pengguna untuk **Unduh seluruh XLSX**.
6. Waktu log disimpan sebagaimana di mesin, belum dikonversi ke zona waktu lain. Pastikan jam/timezone mesin sebelum ekspor.
7. Beri akses `view_fingerprint_data` hanya kepada petugas yang berhak mengakses data kehadiran. Audit perizinan jika menambah role baru.

## Pembatasan dan mitigasi

- Library `rats/zkteco` menggunakan **UDP**, timeout socket bawaan relatif lama (60 detik), dan kompatibilitas bergantung firmware.
- Parser pengguna dari paket mengembalikan password mesin; hasil diproyeksikan ke whitelist dan data password langsung dibuang sebelum penyimpanan.
- `getUser()` / `getAttendance()` bisa mengembalikan array kosong bila firmware tidak membalas dengan data. Respons kosong **tidak memicu penghapusan lokal**. Riwayat tetap mencatat jumlah 0.
- Event absensi yang identik dideduplikasi, tetapi event yang benar-benar memiliki seluruh field identik juga dianggap satu peristiwa. Tidak ada transfer template sidik jari.
- Operasi berisiko seperti hapus pengguna, hapus semua log, shutdown, reset dan penulisan biometrik tidak ditampilkan.
- Jika perangkat memakai password komunikasi tambahan/ADMS/TCP-only, paket existing belum tentu cocok; pengujian langsung perangkat tetap diperlukan.
