# Akses SMA AFBS

Portal sekolah berbasis Laravel dengan dua area utama:

- website publik untuk konten dan layanan sekolah,
- panel admin Filament di `/admin` untuk pengelolaan data internal.

Dokumentasi teknis dan runbook Literasi Numerasi tersedia di [`docs/literacy-numeracy/README.md`](docs/literacy-numeracy/README.md).

Dokumen ini sengaja dibuat ringkas dan hanya memuat hal yang bisa dicek langsung dari repo saat ini.

## Stack

- PHP 8.2
- Laravel 12.55
- Filament 5.4
- Livewire 4.2
- Vite 7
- Tailwind CSS 4
- PHPUnit 11
- Spatie Laravel Permission

## Gambaran singkat

```text
Pengunjung publik
  -> routes/web.php
  -> controller publik
  -> model Eloquent
  -> Blade view

Admin
  -> /admin
  -> AdminPanelProvider
  -> Filament resource/page/widget
  -> model Eloquent
```

## Area publik yang terlihat di repo

Route publik yang saat ini terdaftar:

- `/`
- `/agenda`
- `/agenda/events`
- `/berita`
- `/berita/{news}`
- `/siswa`
- `/siswa/{student}`
- `/tagihan`
- `/tagihan/detail`
- `/tagihan/bayar`
- `/tagihan/{code}`
- `/perpustakaan`
- `/perpustakaan/buku/{book}`
- `/perpustakaan/buku/{book}/download`

Controller publik utama:

- `HomeController`
- `AgendaController`
- `NewsController`
- `StudentController`
- `BillingController`
- `LibraryController`

## Area admin yang terlihat di repo

Admin memakai Filament dengan panel provider di:

- `app/Providers/Filament/AdminPanelProvider.php`

Login admin memakai:

- URL: `/admin/login`
- field login: `username` + `password`
- page class: `app/Filament/Pages/Auth/Login.php`

Role yang saat ini boleh masuk panel, sesuai `User::canAccessPanel()`:

- `admin`

## Standar UI admin

Semua fitur admin Filament di `/admin` harus tetap usable di layar HP.

Aturan minimum untuk perubahan baru:

- tidak boleh ada horizontal overflow di level halaman,
- tombol aksi utama CRUD harus tetap bisa dijangkau tanpa geser layar,
- form admin harus terbaca di layar kecil dan default ke satu kolom pada mobile,
- popup/modal CRUD harus tetap muat di viewport HP,
- tabel admin harus punya perilaku mobile yang jelas untuk kolom sekunder dan action.

Untuk halaman dashboard atau halaman admin yang memakai chart/diagram:

- klik chart harus membuka atau menerapkan daftar yang sudah terfilter sesuai data yang diklik,
- jika chart tidak punya tujuan drilldown yang masuk akal, pertahankan sebagai ringkasan informasional yang jelas,
- perilaku chart harus konsisten antar halaman admin,
- jika satu halaman punya banyak diagram, sediakan kontrol bersama untuk menampilkan/menyembunyikan semua diagram.

Jika menambah atau mengubah fitur admin, cek juga perilaku mobile untuk list, form, modal, filter, dan empty state.
- `tu`
- `bendahara`
- `pamong_putra`
- `pamong_putri`
- `kepala_perpus`
- `guru_uks`
- `guru`

Contoh modul admin yang terlihat dari resource Filament:

- berita dan galeri,
- agenda dan event timeline,
- data siswa, berkas siswa, guru/tendik, berkas guru,
- boarding,
- perpustakaan,
- SPP,
- UKS,
- proker,
- user.

## Catatan struktur data

Repo ini memakai campuran:

- tabel Laravel modern dari migration,
- tabel domain sekolah yang sudah ada sebelumnya.

Artinya, `database/migrations` penting, tetapi tidak selalu menjadi gambaran penuh seluruh schema yang dipakai aplikasi.

## Setup lokal

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
mkdir -p public/storage
```

File upload publik project ini disimpan langsung di `public/storage` lewat konfigurasi disk `public`, bukan di `storage/app/public`.
Jangan mengandalkan `php artisan storage:link` untuk project ini.

Jika perlu mengatur sumber sinkron data API, buka `/admin/pengaturans`, isi bagian **Sinkron Data API**, lalu simpan. Halaman itu hanya menampilkan `Sinkronkan data API` dan `Nama Domain Server`; nilai akan disimpan ke `SERVER_SYNC_API_ENABLED` dan `SERVER_SYNC_DOMAIN` di `.env`.

Untuk menyalin database dan storage server ke komputer lokal, lengkapi variabel `SERVER_SYNC_SSH_*`, `SERVER_SYNC_REMOTE_*`, dan `SERVER_SYNC_STORAGE_PATHS` pada `.env` lokal. Setelah itu buka bagian **Sinkron Data API** lalu klik **Tarik Data Server**. Tombol hanya aktif jika `APP_ENV=local`, database lokal memakai MySQL/MariaDB, dan seluruh konfigurasi SSH/database server sudah lengkap. Proses membuat backup lokal terlebih dahulu, lalu menimpa database serta folder storage lokal dari server.

Server produksi tidak membutuhkan endpoint sinkron khusus. Komputer lokal menjalankan `ssh`, `mysqldump`, dan `scp` langsung ke akun server. Pastikan akun SSH server dapat membaca folder aplikasi dan menjalankan `mysqldump` untuk database aplikasi.

Pada Windows, request web tidak menjalankan OpenSSH secara langsung. Tombol admin menulis antrean ke `storage/app/server-sync/requests`, lalu memicu task `Akses2ServerSyncRunner` yang menjalankan `scripts/run-server-sync.ps1` memakai sesi user Windows. Nama task dan binary `schtasks` dapat diatur melalui `SERVER_SYNC_RUNNER_TASK` dan `SERVER_SYNC_SCHTASKS_BINARY`.

Jika ingin menjalankan semua proses lokal sekaligus:

```bash
composer dev
```

## Environment penting

Periksa minimal nilai berikut:

- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `FILESYSTEM_DISK`
- `QUEUE_CONNECTION`
- `SESSION_DRIVER`
- `CACHE_STORE`

`.env.example` saat ini mengarah ke MySQL lokal.

## Debug cepat

Perintah yang paling berguna saat menelusuri masalah lokal:

```bash
php artisan about
php artisan route:list --path=admin
php artisan migrate:status
php artisan optimize:clear
php artisan pail --timeout=0
php artisan test
```

Log utama ada di:

- `storage/logs/laravel.log`

## Catatan update terbaru

Perapihan terbaru di repo ini berfokus pada dua hal:

- mengurangi beban pengecekan role/permission berulang saat membangun navigasi admin,
- mencegah modul Proker tetap muncul/diakses jika tabel yang dibutuhkan belum tersedia.

## Standar UI admin

Semua fitur admin Filament di `/admin` harus tetap usable di layar HP.

Aturan minimum untuk perubahan baru:

- tidak boleh ada horizontal overflow di level halaman,
- tombol aksi utama CRUD harus tetap bisa dijangkau tanpa geser layar,
- form admin harus terbaca di layar kecil dan default ke satu kolom pada mobile,
- popup/modal CRUD harus tetap muat di viewport HP,
- tabel admin harus punya perilaku mobile yang jelas untuk kolom sekunder dan action.

Untuk halaman dashboard atau halaman admin yang memakai chart/diagram:

- klik chart harus membuka atau menerapkan daftar yang sudah terfilter sesuai data yang diklik,
- perilaku chart harus konsisten antar halaman admin,
- jika satu halaman punya banyak diagram, sediakan kontrol bersama untuk menampilkan/menyembunyikan semua diagram.

Jika menambah atau mengubah fitur admin, cek juga perilaku mobile untuk list, form, modal, filter, dan empty state.

## Dokumen yang dipertahankan

- [docs/architecture.md](docs/architecture.md)
- [docs/debugging.md](docs/debugging.md)
