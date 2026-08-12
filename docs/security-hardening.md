# Hardening Keamanan Aplikasi

Dokumen ini mencatat perlindungan yang harus tetap kompatibel dengan Filament,
Livewire, passkey, dan antrean submit Literasi.

## Transport dan cookie

- Domain produksi wajib mengalihkan HTTP ke HTTPS sebelum request masuk Laravel.
- `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, dan
  `SESSION_SAME_SITE=lax` wajib dipakai di produksi.
- Cookie XSRF tetap dapat dibaca JavaScript/Livewire; jangan mengubahnya menjadi
  HttpOnly. Cookie tersebut tetap wajib Secure pada HTTPS.
- `TRUSTED_PROXIES` tidak boleh memakai `*` tanpa bukti reverse proxy. Produksi
  LiteSpeed langsung memakai `null`; isi alamat proxy secara eksplisit jika
  topologi hosting berubah.

## Header browser

- Middleware `ApplySecurityHeaders` melindungi respons Laravel.
- `public/.htaccess` melindungi respons statis dan melakukan redirect HTTPS.
- CSP dimulai dengan `SECURITY_CSP_MODE=report-only`. Mode `enforce` hanya boleh
  diaktifkan setelah login, passkey, Livewire, MathJax, YouTube, Google Drive,
  dan submit Literasi diuji.
- HSTS dimulai 300 detik. Naikkan bertahap tanpa `includeSubDomains` atau preload
  sebelum semua subdomain SMA AFBS diperiksa.
- Rollback cepat tersedia melalui `SECURITY_HEADERS_ENABLED=false`; redirect
  HTTPS tetap dipertahankan kecuali terbukti membuat loop proxy.

## Dokumen sensitif

- Akses URL langsung ke `public/storage/berkas_guru` ditolak oleh `.htaccess`.
- Preview dan download harus melalui route admin yang memeriksa akun, module
  access, serta scope guru terkait.
- File tetap berada di lokasi lama pada tahap pertama sehingga tidak ada migrasi
  atau penghapusan data produksi.
- Dokumen sensitif lain dipindahkan bertahap ke disk privat setelah inventarisasi
  path database, dry-run, checksum, dan backup selesai.

## Submit Literasi

- Jangan menambah retry di luar kontrak `async-v2`.
- Satu klik hanya boleh menghasilkan satu POST final dan maksimal satu replay
  idempoten setelah status tiket diperiksa.
- Draf bukan bukti tersimpan. UI hanya boleh menyatakan berhasil setelah tiket
  `completed` dan Struk Pengiriman tersedia.
- Header keamanan tidak boleh mengubah limiter, slot, request ID, receipt
  recovery, atau prioritas antrean Literasi.

## Pemeriksaan sebelum deploy

```bash
composer audit --locked --no-dev
npm audit
php artisan test tests/Feature/SecurityHeadersTest.php
php artisan test tests/Feature/LibraryLiteracyProgramTest.php
php artisan view:cache
npm run build
```

Setelah deploy, periksa HTTP redirect, cookie Secure, header HTTPS, login
password/passkey, satu submit Literasi, satu pemulihan Struk, dan satu dokumen
guru melalui route terotorisasi.
