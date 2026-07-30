# Deploy, Rollback, dan Runbook Insiden

## Deploy

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan assessment:install-defaults
php artisan view:cache
php artisan route:list --path=penilaian
php artisan schedule:list
```

Environment produksi:

```dotenv
ASSESSMENT_MODULE_ENABLED=false
ASSESSMENT_REPORT_DISK=local
ASSESSMENT_REPORT_QUEUE=assessment-reports
ASSESSMENT_REPORT_WORKER_MAX_TIME=50
ASSESSMENT_REPORT_WORKER_TIMEOUT=180
DB_QUEUE_RETRY_AFTER=180
```

Cron tetap satu kali per menit:

```text
php artisan schedule:run
```

Setelah deploy:

1. Pastikan `/up` 200 dan login admin normal.
2. Jalankan command defaults dua kali; keduanya harus sukses tanpa duplikat.
3. Biarkan feature flag nonaktif sampai workbook resmi dan akun pilot siap.
4. Pada jendela pilot, aktifkan flag, download template, lalu preview workbook
   resmi sebelum apply.
5. Verifikasi/assign role secara eksplisit: `kurikulum`, `guru_mapel`,
   `wali_kelas`, dan `kepala_sekolah`. Importer tidak mengubah role secara
   diam-diam; khususnya akun wali kelas harus memiliki `penilaian.homeroom`.
6. Periksa juga `users.guru_tendik_id` dan `module_access_levels.penilaian`.
7. Buat satu periode pilot nyata; jangan membuat siswa/data palsu di produksi.
8. Periksa halaman HP 360/390 dan desktop.
9. Saat report dibuat, cek queue Literasi tetap didahulukan dan file berada pada storage privat.

## Rollback aman

1. Set `ASSESSMENT_MODULE_ENABLED=false`.
2. Clear config/cache.
3. Jangan menjalankan migration down dan jangan menghapus tabel assessment.
4. Jangan menghapus snapshot/PDF privat.
5. Revert kode hanya setelah backup database dan `storage/app/private/assessment-reports`.

## Diagnosis cepat

### Menu tidak muncul

- Cek feature flag.
- Cek migration dan `assessment:install-defaults`.
- Cek role/permission user serta `module_access_levels`.
- Clear permission/config cache.

### Guru tidak melihat assignment

- Cek `users.guru_tendik_id`.
- Cek master teaching assignment semester.
- Cek guru/kelas termasuk snapshot periode.
- Jangan memakai `guru_mapel_label` sebagai fallback.

### Save ditolak versi usang

- Ada tab lain menyimpan lebih dahulu.
- Muat ulang assignment, bandingkan draf browser, lalu simpan ulang.
- Jangan menurunkan `lock_version` secara manual.

### Periode tidak dapat dibuka

Baca error preflight: pilihan rombel, siswa aktif, akun guru, wali kelas, skema, komponen, dan bobot 100%.

### PDF antre terlalu lama

- Normal jika submit/analisis Literasi masih aktif.
- Cek `schedule:list`, cron, tabel `jobs`, dan failed jobs.
- Cek `AssessmentReportQueueGate`.
- Pastikan disk `local` dapat ditulis dan tidak mengarah ke webroot.

### Link 404/410

Cek published, expiry, revoked, revisi, file, dan checksum. Jangan memperpanjang token lama; buat link baru.
