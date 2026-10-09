# MikroTik Hotspot (Migrasi dari InvMan)

## Status: nonaktif secara default

Kode eksperimen captive portal pada `yusufbayuw/invman` **tidak disalin sebagai endpoint web**.
Implementasi lama mengandung password hard-coded, akses tanpa autentikasi, dan
asumsi MAC/IP dari query string adalah tepercaya. Kode baru ditempatkan di
`App\\Services\\MikroTikHotspotService` dengan konfigurasi `config/mikrotik_hotspot.php`.
Fitur ini **berbeda** dari NAC WPA3-Enterprise (FreeRADIUS/EAP-TLS) pada
[docs/NAC-WPA3.md](NAC-WPA3.md); jangan menyatukan keduanya sebagai satu alur autentikasi.

## Cakupan yang dipindahkan

- Menggunakan HTTPS RouterOS **v7** REST API; tidak menambah dependensi RouterOS API PHP.
- Memverifikasi pasangan IPv4 dan MAC terhadap daftar `/ip/hotspot/host` sebelum mengizinkan login.
- Menyediakan/memperbarui hanya akun Hotspot milik integrasi ini (`dokumentasi-{user_id}`) dan tidak mengubah akun lain.
- Kata sandi akun Hotspot diturunkan dari HMAC secret server; tidak ada password `invman` atau password router bawaan.
- Menggunakan `/ip/hotspot/active/login` melalui RouterOS REST, hanya setelah semua pemeriksaan lulus.
- Tidak ada endpoint publik, form login Hotspot, atau pemanggilan otomatis saat login Filament. Untuk pemakaian awal tersedia perintah CLI terbatas bagi operator server.

## Persiapan

Pastikan RouterOS v7 dengan `www-ssl`, sertifikat TLS yang dipercaya PHP, dan
service terbatas ke alamat IP server Dokumentasi. Buat akun RouterOS **khusus**
dengan izin minimum untuk membaca host/pengguna dan melakukan provision/login.
Jangan gunakan akun `admin`, password kosong, atau `www` HTTP.

```dotenv
MIKROTIK_HOTSPOT_ENABLED=false
MIKROTIK_HOTSPOT_URL=https://router.example.internal
MIKROTIK_HOTSPOT_USERNAME=dedicated-rest-operator
MIKROTIK_HOTSPOT_PASSWORD=REPLACE_WITH_STRONG_SECRET
MIKROTIK_HOTSPOT_USER_SECRET=REPLACE_WITH_RANDOM_SECRET_MINIMUM_32_CHARACTERS
MIKROTIK_HOTSPOT_PROFILE=default
MIKROTIK_HOTSPOT_CA_BUNDLE=
```

Set CA bundle menjadi path absolut jika memakai CA privat; jangan menonaktifkan
verifikasi TLS. Secret tidak boleh masuk Git, log, atau output CLI. Pastikan
`APP_KEY` tetap terjaga. Setelah pengujian dalam jaringan terisolasi, aktifkan
`MIKROTIK_HOTSPOT_ENABLED=true` dan jalankan `php artisan config:clear`.

## Pengujian terkendali (operator shell)

```bash
php artisan hotspot:connect-user 42 AA:BB:CC:DD:EE:FF 10.10.1.25 --confirm
php artisan test --filter=MikroTikHotspot
```

Perintah wajib menyertakan `--confirm`, hanya bisa dijalankan dari CLI,
memerlukan pengguna aktif dan host yang sudah diketahui router. Perintah ini
menyebabkan perubahan nyata pada router. Lakukan uji di staging dahulu,
periksa audit/log RouterOS, profil/masa aktif akun, dan mekanisme revokasi.

## Batasan dan pekerjaan berikutnya

- Tidak ada **captive redirect end-user otomatis**. Sebelum dibuat, harus ada
  bukti konteks klien yang tepercaya dari router (bukan MAC/IP dari URL biasa),
  proteksi replay/CSRF, rate limit, audit, dan keputusan apakah tetap memakai
  Hotspot atau beralih sepenuhnya ke NAC EAP-TLS.
- HMAC secret menggantikan static shared password lama tetapi memerlukan
  prosedur rotasi; akun yang sudah dibuat tetap ada pada RouterOS sampai
  dikelola/dinonaktifkan. Jangan aktifkan untuk semua pengguna secara otomatis.
- `active/login` perlu diverifikasi dengan firmware/konfigurasi Hotspot asli;
  pengujian HTTP lokal bukan bukti perangkat riil berhasil login.
- Tidak melakukan perubahan router, switch, UniFi, VLAN, RADIUS, atau SSID saat deploy.

Referensi RouterOS: https://manual.mikrotik.com/docs/developer-guides/rest-api/
