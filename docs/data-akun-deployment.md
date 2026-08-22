# Runbook Deploy: Menu Data Akun (WiFi & Belajar.id) + Push Preview Nilai Lama→Baru

**Aplikasi:** akses2-laravel (Laravel 12 + Filament v5) — prod `app.smaafbs.sch.id`, deploy `/home/sman5479/akses-app`.
**Aplikasi sumber WiFi:** `pantaumikrotik` — prod `mikrotik.smaafbs.sch.id`.
**Sifat:** semua perubahan preview→konfirmasi→simpan; tidak ada hapus/otomatis apply.

> Runbook ini menambah fitur menu Data Akun (branch `feat/data-akun-menu`) dan penyempurnaan preview push (branch `feat/student-server-push`). Keduanya perlu di-merge & dideploy secara terkontrol. TIDAK menjalankan `git reset --hard`/`git clean` di server (tree/storage bisa dirty).

## 0. Prasyarat & keputusan
- Fase A (API baca hotspot) sudah di branch `feat/api-baca-hotspot` repo pantaumikrotik → **buka PR & merge** dulu bila mode sinkron otomatis ingin dipakai.
- Backup DB produk sebelum migrasi apa pun.
- Aktifkan fitur bertahap (default nonaktif).

## 1. akses2-laravel — migrasi baru
Tiga migrasi (idempotent add-column & tabel baru), jalankan `--path` satu per satu setelah backup:
- `2026_08_21_090000_create_account_categories_table.php`
- `2026_08_21_090100_add_identity_fields_to_hotspot_users_table.php` (kolom `role`,`nama`,`kelas`,`input_mode`,`category_id` di `hotspot_users`)
- `2026_08_21_090200_create_belajar_id_accounts_table.php`

```
php artisan migrate --path=database/migrations/2026_08_21_090000_create_account_categories_table.php --force
php artisan migrate --path=database/migrations/2026_08_21_090100_add_identity_fields_to_hotspot_users_table.php --force
php artisan migrate --path=database/migrations/2026_08_21_090200_create_belajar_id_accounts_table.php --force
```

## 2. Menu & akses
- Menu **IT SMA AFBS** kehilangan item MikroTik (Monitor/HotspotSettings/HotspotUser/BlockedDomain) — dinonaktifkan dari navigasi (route tetap ada, reversibel).
- Item baru:
  - Grup **Siswa**: Data Akun WiFi (Siswa), Data Akun Belajar.id (Siswa).
  - Grup **Guru**: Data Akun WiFi (Guru), Data Akun Belajar.id (Guru), Kategori Akun Guru.
- Akses mengikuti gerbang `HotspotAccessible` (admin penuh atau item diizinkan per-user). Cek user non-admin bila perlu diberi `allowed_navigation_items`.

## 3. Import Excel
- **Belajar.id**: kolom `NAMA, STATUS, EMAIL, PASSWORD`. STATUS `guru`/`tendik` → menu Guru; selain itu (kelas) → menu Siswa. Upsert per email. Template: tombol "Download Template".
- **WiFi (jembatan)**: kolom `USERNAME, PASSWORD, PROFIL, KELAS, ROLE`. ROLE `guru` → Guru; lainnya Siswa. Upsert per username; ditandai sumber `otomatis`.

## 4. Sinkron WiFi otomatis (opsional, butuh Fase A merged)
Config `config/wifi_sync.php` via `.env` (placeholder di `.env.example`):
```
WIFI_SYNC_ENABLED=true
WIFI_SYNC_BASE_URL=https://mikrotik.smaafbs.sch.id
WIFI_SYNC_TOKEN=<token acak >=32 char, SAMA dengan api_hotspot_token di server pantaumikrotik>
WIFI_SYNC_TIMEOUT=30
```
- Di server pantaumikrotik: set `api_hotspot_token` di `config.local.php` (nilai nyata, JANGAN commit).
- Tombol "Sinkron dari MikroTik" muncul di Data Akun WiFi (Siswa) saat `WIFI_SYNC_ENABLED=true`.
- Aksi: baca (read-only) → preview (baru/berubah/sama) → simpan (upsert, tanpa hapus).
- Setelah ubah `.env`: rebuild cache config (`php artisan config:clear && php artisan config:cache`).

## 5. Push data siswa → server: preview nilai lama→baru
- Preview sekarang menampilkan per field: nilai server (lama) → nilai lokal (baru), **hanya** untuk field `data_siswa` yang di-allowlist (denylist + `id` dikecualikan).
- Nilai discalarkan & dipotong 200 char; struktur asing/kredensial tidak ditampilkan; snapshot tetap terenkripsi.
- Apply tetap manual (tombol terpisah), idempoten, tanpa hapus/buat siswa.

## 6. Verifikasi pasca-deploy
- `php artisan optimize:clear` sukses; `route:list` memuat resource baru.
- Login `/admin` → cek menu Siswa & Guru tampil item baru; menu IT SMA AFBS tidak lagi menampilkan komponen MikroTik.
- Uji import Excel kecil (1-2 baris) untuk Belajar.id & WiFi.
- Bila sinkron diaktifkan: klik Sinkron → verifikasi jumlah preview vs data.
- Push preview: muat preview, pastikan nilai lama→baru tampil hanya untuk field izin.

## 7. Rollback
- Fitur nonaktif via `.env` (WIFI_SYNC_ENABLED=false; STUDENT_SYNC_* false) + rebuild config.
- Migrasi punya `down()` (drop kolom/tabel baru) — jalankan `migrate:rollback --step` hanya bila diperlukan & sudah backup.
- Menu MikroTik dapat dikembalikan dengan membalik `shouldRegisterNavigation()` → true dan memasukkan lagi ke `CLASS_PARENT_MAP`.
- **JANGAN** `git reset --hard`/`git clean` di server.
