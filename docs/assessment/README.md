# Penilaian ASTS–ASAS

Dokumen ini adalah pintu masuk wajib bagi pengembang dan AI yang mengubah modul Penilaian.

## Urutan baca

1. [Arsitektur data dan workflow](architecture-and-workflow.md)
2. [Workbook dan impor master](master-import.md)
3. [Rapor, antrean, dan keamanan](reports-and-operations.md)
4. [Deploy, rollback, dan runbook insiden](deployment-runbook.md)
5. [Riwayat perubahan](CHANGELOG.md)

## Invariant yang tidak boleh dilanggar

- ASTS dan ASAS selalu dibedakan oleh `assessment_period_id`; nilai antarperiode tidak boleh saling menimpa.
- Master lama hanya menjadi referensi logis. Transaksi memakai snapshot nama siswa, kelas, guru, dan mapel.
- Periode dibuka melalui preflight atomik, bukan dengan mengubah kolom `status` secara langsung.
- Nilai `null` berarti belum diisi, bukan nol.
- Penyimpanan guru adalah batch satu assignment melalui tombol **Simpan Draf**, disertai `lock_version`.
- Total bobot komponen aktif wajib tepat 100%.
- Hanya hasil ASTS terkunci yang dapat disalin ke komponen ASAS, bersama ID sumber dan snapshot angka.
- Snapshot rapor immutable. Perubahan setelahnya membuat revisi baru dan tidak menimpa PDF lama.
- PDF siswa baru dirender dari snapshot tanpa file permanen. Cache PDF kelas dan PDF historis tetap memakai disk `local` privat (`storage/app/private`), tidak pernah memakai disk publik.
- Queue `assessment-reports` hanya berjalan ketika submit dan queue Literasi/default sedang kosong, satu job per putaran.
- Semua action sensitif diperiksa lagi di Policy/service, bukan mengandalkan menu tersembunyi.
- Seluruh halaman admin harus tetap usable pada HP sesuai `AGENTS.md`.

## Peta kode

- Schema: `database/migrations/2026_07_31_080000_create_assessment_foundation_tables.php` dan kategori assignment pada `2026_08_06_150000_add_assessment_subject_categories.php`
- Ekstensi rapor tiga halaman: `database/migrations/2026_07_31_120000_extend_assessment_report_structure.php`
- Enum: `app/Enums/Assessment`
- Model: `app/Models/Assessment`
- Policy: `app/Policies/Assessment`
- Workflow: `app/Actions/Assessment`
- Kalkulator: `app/Support/Assessment/AssessmentCalculator.php`
- Impor: `app/Support/AssessmentMaster` dan `app/Exports/AssessmentMasterTemplateExport.php`
- Data awal plotting 2026/2027: `php artisan assessment:teaching-plan-2026` untuk preview, lalu `--apply` setelah seluruh guru dan kelas cocok.
- Admin: `app/Filament/Pages/Assessment` dan resource `Assessment*Resource`
- Reporting: `app/Support/Assessment/Reporting`, `app/Jobs/Assessment`, dan `AssessmentReportController`
- PDF: `resources/views/assessment/reports`
- Layout/preflight/format nilai: `app/Support/Assessment/Reporting` dan `app/Support/Assessment/AssessmentNumberFormatter.php`
- Scheduler: `routes/console.php`

Jangan menaruh token share, isi rapor siswa, kredensial, atau isi `.env` di dokumentasi/log publik.
