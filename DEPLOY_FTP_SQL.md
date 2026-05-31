# Deploy FTP dan SQL Manual

Panduan ini dipakai jika server tidak menjalankan `npm ci`, `npm run build`, atau `php artisan migrate`.

## 1. Build dari lokal

Build frontend cukup dilakukan di komputer lokal sebelum upload FTP.

```bash
npm run build
```

Hasil build yang wajib ikut diupload:

```text
public/build/manifest.json
public/build/assets/app-mcHV0BYP.css
public/build/assets/app-DVGSChis.js
```

## 2. Upload via FTP

Upload perubahan aplikasi ke folder project server. Untuk update boarding rapot ini, minimal upload folder/file berikut:

```text
app/
bootstrap/app.php
database/migrations/
database/sql/2026_05_31_boarding_rapot_update.sql
public/build/
public/js/filament-admin-fallback.js
resources/views/
routes/web.php
```

Jika server tidak menjalankan Composer, upload juga folder `vendor/` dari lokal yang sudah berjalan.

Jangan hapus atau timpa sembarangan:

```text
.env
public/storage/
storage/app/
storage/logs/
```

Upload user/photo/file aplikasi berada di `public/storage`, jadi folder itu harus dipertahankan.

## 3. Bersihkan cache tanpa SSH

Jika tidak bisa menjalankan `php artisan optimize:clear`, bersihkan cache melalui FTP:

```text
hapus file *.php di bootstrap/cache/
hapus file *.php di storage/framework/views/
hapus isi storage/framework/cache/data/ jika ada
```

Jangan hapus file `.gitignore` dan jangan hapus foldernya.

## 4. Import SQL

Backup database dulu, lalu import:

```text
database/sql/2026_05_31_boarding_rapot_update.sql
```

Import bisa lewat phpMyAdmin atau command MySQL:

```bash
mysql -u DB_USER -p DB_NAME < database/sql/2026_05_31_boarding_rapot_update.sql
```

SQL ini aman untuk dijalankan ulang pada update yang sama. Jika tabel dasar boarding belum ada, SQL akan berhenti dengan pesan tabel mana yang kurang.

## 5. Cek setelah upload

Buka halaman berikut:

```text
/admin/boarding-hafalan-points
/admin/boarding-pencapaians
/admin/boarding-rapots
```

Pastikan menu Materi Boarding, Materi MT, edit rapot manual, preview rapot, dan import data pencapaian tampil normal.
