# Hasil Hermes Feature Parity Design

**Tanggal:** 2026-08-20
**Status:** Disetujui untuk dijadikan spesifikasi
**Proyek sumber:** `E:/xampp/htdocs/hasil-hermes`
**Proyek target:** `E:/xampp/htdocs/akses2-laravel`

## 1. Tujuan

Memindahkan seluruh fitur operasional dan seluruh data lama aplikasi `hasil-hermes` ke `akses2-laravel`, menyesuaikan antarmuka dengan Laravel 12 dan Filament v5, serta menjadikan Laravel/MySQL sebagai satu-satunya sumber data utama. Setelah proses penerimaan selesai, `hasil-hermes` dipertahankan hanya sebagai arsip/backup read-only.

Fitur hotspot ditempatkan pada hierarki navigasi admin **Manajemen Sekolah → IT SMA AFBS**. Integrasi tetap menggunakan router MikroTik sekolah dan mempertahankan perilaku RouterOS 6.48.6 yang telah terbukti diperlukan: mutasi dilakukan melalui koneksi segar, koneksi ditutup setelah mutasi, lalu hasil diverifikasi melalui koneksi baru dengan retry terbatas.

## 2. Keputusan Produk yang Disepakati

1. Seluruh data lama dipindahkan: akun lokal, pengaturan, domain blokir, riwayat sesi, pemetaan IP-user, dan riwayat akses.
2. Laravel menjadi aplikasi utama dan sumber data utama setelah cutover.
3. `hasil-hermes` tidak menjalankan sinkronisasi dua arah setelah cutover; aplikasinya disimpan sebagai arsip/backup.
4. Seluruh halaman hotspot masuk ke area admin Filament pada kelompok **IT SMA AFBS**.
5. Migrasi dilakukan bertahap agar setiap bagian dapat diuji sebelum menyentuh bagian berikutnya.
6. Operasi RouterOS dapat dijalankan di lingkungan Laravel mana pun yang dapat mencapai router. Ketika router tidak terjangkau, UI tetap dapat membaca/mengelola data lokal, menampilkan status offline, dan tidak mengklaim mutasi router berhasil.
7. Scheduler arsip Laravel hanya aktif melalui feature flag dan harus gagal secara aman ketika router tidak terjangkau. Scheduler pengecualian IP tetap berada di RouterOS karena harus terus bekerja meskipun aplikasi Laravel sedang offline.

## 3. Ruang Lingkup

### 3.1 Dashboard IT SMA AFBS

Dashboard khusus IT menampilkan:

- Status koneksi, identity, versi RouterOS, board, dan uptime.
- CPU, jumlah core, RAM, storage, dan status interface.
- Jumlah akun router, akun lokal, akun online, akun disabled, dan akun lokal yang belum terkirim.
- Jumlah domain lokal, domain tersinkron, dan status rule firewall.
- Status DNS lock, anti-DoH/DoT, monitoring akses, script pengecualian, dan scheduler RouterOS.
- Daftar IP interface router yang dapat digunakan sebagai host API.
- Tombol **Aktifkan Semua** yang idempoten: memastikan rule blokir, pengecualian, DNS lock, anti-bypass, monitoring akses, sinkron domain, arsip log, dan snapshot.

Tombol aktivasi harus menampilkan hasil per langkah. Keberhasilan sebagian tidak boleh dilaporkan sebagai keberhasilan penuh.

### 3.2 Akun Hotspot

Halaman akun menggabungkan akun router dan database Laravel dengan status sumber:

- `router`: hanya ditemukan di router.
- `local`: hanya ditemukan di database Laravel.
- `both`: ditemukan di kedua sisi dan data penting konsisten.
- `conflict`: ditemukan di kedua sisi tetapi field penting berbeda.

Kemampuan yang disediakan:

- Cari, filter profil, filter status, dan pagination Filament.
- Tambah, edit/rename, hapus, enable, dan disable akun.
- Bulk enable, disable, delete, dan push ke router.
- Push satu atau semua akun lokal ke router.
- Pull/upsert akun router ke Laravel tanpa merusak metadata Laravel.
- Durasi hari dikonversi ke `limit-uptime`; nilai nol benar-benar menghapus limit lama.
- Profile fallback hanya dilakukan ketika pengguna menyetujuinya atau saat import dengan kebijakan fallback eksplisit; hasil fallback dicatat.
- Password hotspot tetap dapat diakses oleh admin berwenang, tetapi disimpan terenkripsi di database Laravel dan hanya dibuka saat diperlukan.

Mutasi router dan database menggunakan hasil yang dapat direkonsiliasi. Jika router gagal, record lokal ditandai `local`/`conflict`, bukan dilaporkan tersinkron.

### 3.3 Profil/Grup Bandwidth

Halaman profil menampilkan nama, `rate-limit`, `shared-users`, status default, dan jumlah anggota. Fitur:

- Membaca profil yang sudah ada di router.
- Menetapkan profil default/fallback di Laravel.
- Mengedit `rate-limit` dan `shared-users` pada profil router yang valid.
- Normalisasi input seperti `5/5` menjadi `5M/5M`.
- Menghapus profil dengan pilihan memindahkan anggota atau menghapus anggota.
- Generator akun siswa memakai profil kelas yang sudah dibuat melalui Winbox (`X 1`, `X 2`, `XI 1`, `XI 2`, `XII 1`, `XII 2`, `XII 3`) dan tidak mencoba membuat profil kelas melalui API.

Karena RouterOS 6.48.6 sekolah menolak profil hasil pembuatan API pada validasi user-add, pembuatan profil baru melalui Laravel tidak menjadi alur utama. UI memberikan instruksi membuat profil baru di Winbox, kemudian melakukan refresh daftar profil.

### 3.4 Generator Akun Siswa

Generator membaca `data_siswa` aktif dan menyesuaikan nama serta `rombel_saat_ini`:

- Filter semua rombel atau satu rombel.
- Username dari slug nama dengan batas aman dan suffix untuk duplikat.
- Password berdasarkan tanggal lahir, username, empat digit NIPD, atau empat digit NISN.
- Preview, seleksi akun, dan laporan siswa tanpa tanggal lahir.
- Profil dipetakan langsung dari nama rombel ke profil kelas yang sudah ada.
- Catatan router menyimpan nama siswa lengkap.
- Akun yang sudah ada tidak dibuat ulang; konflik ditampilkan untuk ditindaklanjuti.

### 3.5 Import dan Export

Import mendukung XLSX dan CSV maksimum 2.000 baris:

- Drag-and-drop/upload, preview, simpan, dan batal.
- Header fleksibel berbahasa Indonesia serta file tanpa header.
- CSV `,`/`;`, BOM, UTF-8, dan Windows-1252.
- Kolom: username, password, profil, durasi, harga, catatan, dan tanggal kedaluwarsa.
- Validasi field wajib, duplikat database, duplikat file, dan profil.
- Opsi push langsung ke router dengan laporan sukses, gagal, dan fallback.
- Template XLSX/CSV menggunakan profil router yang tersedia.

Export menyediakan XLSX/CSV seluruh akun atau hasil filter, termasuk password yang hanya dapat diekspor oleh pengguna berizin khusus.

### 3.6 Monitoring Router dan Sesi

Monitoring mempertahankan kemampuan yang telah tersedia dan menambahkan:

- Detail core CPU, error interface, status running/down/disabled.
- User online: user, IP, MAC, uptime, host/server, login-by, download, dan upload.
- Refresh terkontrol tanpa membebani router.
- Arsip login, logout, dan percobaan login.
- Pasangan login-logout, durasi sesi, dan penanda sesi masih online.
- Pencarian user/IP serta batas/pagination yang aman.

### 3.7 Riwayat Akses

Laravel mengelola tiga rule logging RouterOS untuk DNS, HTTP, dan HTTPS pada koneksi baru. Proses arsip:

- Menarik log router secara idempoten.
- Mengonversi timezone router ke `Asia/Jakarta`.
- Mengambil hostname terbaik dari DNS cache ketika tersedia.
- Menghapus duplikasi berdasarkan `log_id` stabil.
- Mengatribusikan IP ke user berdasarkan periode sesi.
- Menyajikan ringkasan top host per user dan rincian koneksi.
- Filter user dan periode 1/7/14/30 hari atau semua.
- Opsi menyembunyikan host sistem/background.
- Pembersihan per umur, per user, atau seluruh data.
- Retensi default: akses 14 hari, maksimal 100.000 akses, dan 5.000 event sesi; seluruh angka dapat dikonfigurasi.

### 3.8 Blokir Situs dan Pengecualian

Database Laravel menjadi sumber kebenaran domain. Fitur mencakup:

- Normalisasi URL/domain dan varian `www`.
- Catatan/kategori domain.
- Preset: pornografi, judi, dating, game, streaming, media sosial, TikTok, YouTube, Instagram, Facebook, X, WhatsApp, dan Telegram.
- Tambah/hapus satu domain atau kategori secara massal.
- Sinkronisasi ke firewall address-list dan verifikasi koneksi segar.
- Ringkasan per kategori, status sinkron, dan IP hasil resolve.
- Rule `forward drop` untuk blocklist.
- DNS lock UDP/TCP port 53.
- Anti-bypass DoT port 853 dan DoH resolver publik.
- Enable, disable, perbaikan rule parsial, dan status per rule.
- Pengecualian profil global serta pengecualian profil per kategori.
- Router script dan scheduler `hh-exempt-refresh` setiap satu menit.
- Migrasi domain antar-address-list ketika kebijakan kategori berubah.

Semua operasi harus idempoten dan memverifikasi rule/script/scheduler satu per satu.

### 3.9 Pengaturan dan Operasional

- Host, port, username, dan password RouterOS.
- Password kosong pada form edit mempertahankan secret lama.
- Tes koneksi dan snapshot router.
- Artisan command untuk tes router, arsip log, status/apply/off pengecualian, migrasi data lama, dan setup/reset VPN L2TP/IPsec.
- Setup VPN tetap berupa command admin, bukan tombol web biasa, karena mengubah konfigurasi jaringan sensitif.
- Reset modul Laravel tidak menghapus seluruh database aplikasi. Reset dibatasi ke tabel modul hotspot, memerlukan konfirmasi berlapis, backup, dan otorisasi khusus.

## 4. Arsitektur

### 4.1 Batas Komponen

- `RouterOSClientInterface`: kontrak perintah RouterOS yang dapat diganti fake dalam test.
- `RouterOS`: implementasi socket binary untuk router nyata.
- `HotspotAccountService`: akun, sinkronisasi, durasi, dan rekonsiliasi.
- `HotspotProfileService`: pembacaan/edit profil dan validasi profil kelas.
- `HotspotBlocklistService`: domain, kategori, firewall, DNS lock, dan pengecualian.
- `HotspotArchiveService`: log sesi, pemetaan IP-user, akses, deduplikasi, dan retensi.
- `HotspotActivationService`: orkestrasi tombol **Aktifkan Semua** dan hasil per langkah.
- `LegacyHotspotImporter`: pembacaan SQLite lama dan migrasi idempoten ke MySQL.

Filament Resource/Page hanya menangani formulir, tabel, otorisasi, dan notifikasi. Logika RouterOS tidak ditempatkan di class UI.

### 4.2 Model dan Tabel

Tabel yang sudah ada tetap digunakan dan diperluas:

- `hotspot_users`
- `blocked_domains`
- `hh_settings`

Tabel baru:

- `hotspot_profiles`: cache profil, default, rate, shared users, dan waktu sinkron.
- `hotspot_session_events`: event login/logout/percobaan.
- `hotspot_ip_user_periods`: rentang atribusi IP-user.
- `hotspot_access_logs`: koneksi yang diarsipkan.
- `hotspot_activation_runs`: audit hasil aktivasi per langkah.
- `hotspot_sync_runs`: audit import/sinkronisasi.

Kategori domain disimpan sebagai kolom terindeks pada `blocked_domains`; konfigurasi pengecualian disimpan dalam format terstruktur di tabel settings atau tabel kebijakan khusus bila query relasional dibutuhkan dalam implementasi.

### 4.3 Secret dan Otorisasi

- Secret RouterOS/VPN serta password hotspot menggunakan encrypted cast atau layanan enkripsi Laravel.
- Secret tidak ditulis ke log, exception, notification, atau snapshot.
- Semua halaman menggunakan policy/trait akses modul yang sama, termasuk direct-route authorization.
- Operasi export password, reset, VPN, dan aktivasi penuh memiliki izin terpisah.
- Admin penuh tetap memperoleh semua item navigasi melalui mekanisme `allowed_navigation_items` yang sudah digunakan proyek.

## 5. Migrasi Data Lama

Artisan command menerima path SQLite eksplisit dan menjalankan mode preview sebelum apply. Prosedur:

1. Pastikan file SQLite dapat dibaca dan tabel sumber lengkap.
2. Buat backup database MySQL untuk tabel target.
3. Hitung record setiap tabel sumber.
4. Migrasikan secara batch dengan transaksi per tabel dan upsert idempoten.
5. Petakan `users` ke `hotspot_users`, mempertahankan harga, expired, catatan, dan timestamp.
6. Petakan `blocked_domains`, settings non-secret, sesi, periode IP-user, dan akses.
7. Enkripsi secret saat masuk ke Laravel.
8. Jangan menghapus atau mengubah SQLite sumber.
9. Tampilkan jumlah sumber, insert, update, skip, konflik, dan gagal.
10. Jalankan verifikasi count dan sampling hash field non-secret.

Akun admin lama tidak menggantikan user Laravel. Identitas admin lama hanya diarsipkan dalam laporan migrasi; autentikasi tetap menggunakan `users` Laravel dan role/permission yang ada.

## 6. Penanganan Error dan Konsistensi

- Router offline: data lokal boleh disimpan sebagai pending/local, tetapi notifikasi harus menyatakan router belum berubah.
- Mutasi RouterOS: fresh connection → mutate → close → fresh connection → verify → satu retry terkontrol → hasil gagal yang eksplisit.
- Mutasi multi-item mencatat hasil per item dan tidak melakukan rollback semu terhadap router.
- DB menggunakan transaksi untuk perubahan lokal yang saling bergantung.
- Rule parsial dibaca per spesifikasi; satu rule tidak dianggap mewakili seluruh proteksi.
- Import dapat dilanjutkan hanya setelah preview lolos validasi.
- Scheduler menggunakan mutex/`withoutOverlapping`, timeout, dan log channel khusus tanpa secret.

## 7. Strategi Pengujian

Pengembangan mengikuti TDD:

- Unit test protocol parsing, durasi, rate normalization, domain normalization, username, retensi, dan data mapping.
- Contract test service menggunakan fake `RouterOSClientInterface` untuk koneksi gagal, trap, phantom response, retry, dan verifikasi.
- Feature/Livewire test seluruh action Filament penting dengan authorization.
- Migration test memakai salinan SQLite fixture tanpa secret nyata dan MySQL test database.
- Command test preview/apply/idempotency.
- Smoke test route admin dan navigasi **IT SMA AFBS**.
- Uji router nyata dilakukan terakhir menggunakan akun/domain prefix khusus tes dan selalu disertai cleanup serta read-back verification.

Tidak ada test otomatis rutin yang boleh memutasi router sekolah.

## 8. Tahapan Implementasi

### Tahap 1 — Fondasi dan Migrasi Data

Perbaikan bug integrasi saat ini, interface RouterOS yang dapat diuji, tabel baru, enkripsi secret, importer SQLite, dan verifikasi migrasi.

### Tahap 2 — Akun, Profil, Import, Export

Rekonsiliasi akun, bulk actions, profil bandwidth, generator siswa berbasis profil yang sudah ada, import preview, template, dan export.

### Tahap 3 — Monitoring, Sesi, dan Riwayat Akses

Dashboard IT, monitoring lanjutan, event sesi, atribusi IP-user, arsip akses, filter, retensi, dan scheduler Laravel.

### Tahap 4 — Blokir dan Pengecualian

Kategori/preset, verifikasi firewall, DNS lock, anti-bypass, pengecualian global/per kategori, script dan scheduler RouterOS.

### Tahap 5 — Operasional dan Cutover

Command operasional/VPN, permission akhir, smoke test, uji router nyata terkontrol, migrasi final, dan perubahan `hasil-hermes` menjadi arsip read-only.

Setiap tahap menghasilkan perangkat lunak yang dapat diuji dan memiliki acceptance gate sendiri. Tahap berikutnya tidak dimulai sebelum tes tahap aktif lulus dan diff ditinjau.

## 9. Kriteria Penerimaan

Migrasi dinyatakan selesai hanya jika:

1. Seluruh menu dan kemampuan operasional yang tercantum dalam ruang lingkup tersedia di Laravel.
2. Seluruh record SQLite lama dimigrasikan atau tercatat sebagai konflik dengan alasan yang dapat ditindaklanjuti.
3. Count dan sampling verifikasi data lulus.
4. Seluruh tes hotspot lulus tanpa memerlukan router nyata.
5. Smoke test UI dan otorisasi lulus.
6. Uji terkontrol pada router membuktikan create/update/delete/enable/disable, profil, blocklist, DNS protection, logging, dan pengecualian.
7. Tidak ada secret di log, git diff, atau output test.
8. Laravel menjadi satu-satunya aplikasi yang melakukan perubahan setelah cutover.
9. Backup SQLite dan backup tabel MySQL tersimpan dan dapat dipulihkan.

## 10. Di Luar Ruang Lingkup

- Generator/cetak voucher atau PDF tidak ditambahkan karena fitur tersebut tidak ada di `hasil-hermes` yang diaudit.
- Multi-router tidak ditambahkan karena aplikasi sumber hanya mendukung satu router.
- Sinkronisasi dua arah dengan `hasil-hermes` setelah cutover tidak dibuat.
- Perombakan modul Laravel selain hotspot tidak dilakukan.
