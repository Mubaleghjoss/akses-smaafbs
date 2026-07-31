# Rapor, Antrean, dan Keamanan

## Snapshot dan PDF

- Periode harus `locked` atau `published`.
- Template harus sama tipe dengan periode.
- Pembuatan revisi wajib alasan.
- Snapshot JSON membekukan identitas, hasil mapel, wali kelas, template, tanda tangan, dan formula.
- Job membaca snapshot, bukan tabel live.
- PDF siswa dan gabungan kelas disimpan pada disk `local`, di bawah `storage/app/private/assessment-reports`.
- Penyimpanan atomik menghasilkan checksum SHA-256.
- Status PDF: `not_scheduled`, `pending`, `processing`, `completed`, `failed`,
  dan `cancelled`.
- Satu `assessment_report_generation_runs` mengendalikan progres untuk pasangan
  periode-template-revisi tanpa mengubah snapshot immutable.
- Job memakai `WithoutOverlapping`, retry/backoff, dan aman dijalankan ulang.
- Lock job dibagi antara nama job legacy dan canonical agar payload antrean dari
  deploy sebelumnya tidak dapat menulis PDF yang sama secara paralel.
- Publish menyimpan pasangan template dan revisi aktif pada settings periode,
  serta memvalidasi checksum PDF siswa dan PDF gabungan seluruh kelas.
- Revisi langsung pada periode published hanya boleh memakai template yang sama,
  memerlukan hak publish, mencabut tautan lama, dan mengembalikan periode ke
  `locked` sampai revisi baru selesai serta dipublish ulang.

## Prioritas shared hosting

Scheduler setiap menit menjalankan urutan:

1. Queue Literasi/default diproses lebih dahulu.
2. `AssessmentReportQueueGate` menolak jalan jika masih ada job prioritas atau aktivitas submit Literasi.
3. Jika aman, satu job `assessment-reports` diproses, maksimal sekitar 50 detik.

Jangan menjalankan worker assessment permanen/paralel pada shared hosting. Jangan memasukkan PDF ke queue `default`.

## Pipeline PDF per kelas

1. Pembuatan revisi membekukan snapshot seluruh siswa, tetapi memberi status
   awal `not_scheduled` dan tidak membuat job per siswa.
2. Admin memilih kelas. Sistem mengirim sekitar satu
   `GenerateClassReportPipeline` untuk setiap kelas aktif.
3. Satu putaran memproses maksimal
   `ASSESSMENT_REPORT_STUDENTS_PER_JOB` siswa (default 3) atau maksimal
   `ASSESSMENT_REPORT_PIPELINE_MAX_SECONDS` detik (default 40).
4. Bila masih ada siswa, job menjadwalkan kelanjutan dirinya. Setelah semua PDF
   siswa valid, pipeline membuat satu PDF gabungan kelas.
5. **Lanjutkan Kelas Terpilih** memakai revisi yang sama; tidak membuat snapshot
   baru dan tidak mengulang PDF yang sudah selesai.

Tombol **Hentikan Semua Antrean PDF** memerlukan alasan dan konfirmasi dua
tahap. Aksi ini hanya menghapus baris queue bernama tepat
`assessment-reports`, menandai record `pending/processing` sebagai
`cancelled`, dan mempertahankan snapshot, PDF selesai, checksum, serta riwayat.
Queue `default` dan Literasi tidak disentuh. Job yang sedang merender
menyelesaikan unit aman saat ini, lalu melihat status batal dan tidak
menjadwalkan kelanjutan.

Rekonsiliasi CLI selalu dry-run secara bawaan:

```bash
php artisan assessment:reports-reconcile-queue
php artisan assessment:reports-reconcile-queue --apply --actor=1 \
  --reason="Migrasi antrean PDF lama ke pipeline per kelas."
```

## Download panel

- Controller memanggil Policy.
- File divalidasi ulang lewat status dan checksum.
- Respons memakai `private, no-store`, `nosniff`, dan `noindex`.
- Download dicatat pada audit.
- PDF gabungan kelas hanya tersedia melalui panel.

## Share link

- Hanya rapor individual pada periode `published`.
- Pembuatan tautan dipisahkan dari render PDF, dijalankan langsung tanpa queue,
  dan dibatasi maksimal 50 siswa sekali proses.
- Token acak 32 byte; database hanya menyimpan SHA-256 token.
- Masa berlaku: 1, 3, atau 7 hari (default UI 1 hari/24 jam).
- Token dapat dicabut, terkena rate limit, dan setiap download diaudit.
- Reopen/regenerate mencabut link revisi lama.
- Link hanya dapat dibuat dan dipakai untuk pasangan template-revisi terbaru
  yang tercatat sebagai set published.
- Token plaintext hanya ditampilkan saat dibuat; tidak bisa dipulihkan dari database.

## Retry

Gunakan tombol **Coba Lagi** hanya pada record `failed`. Sistem mengubah status
kelas/snapshot ke `pending` dan melanjutkan pipeline kelas. Jangan menghapus
record snapshot/artifact untuk mengulang.

Retry revisi historis atau PDF yang sudah completed ditolak oleh server,
meskipun method Livewire dipanggil secara langsung.

## Preview dan watermark

- Preview memakai satu snapshot siswa nyata, dirender sinkron tanpa
  snapshot/job/file permanen, diberi label **Pratinjau—bukan rapor resmi**,
  rate limit, Policy, `no-store`, dan `noindex`.
- Watermark template hanya menerima PNG/JPEG/WebP pada disk `local` privat,
  dioptimalkan GD menjadi PNG maksimal 1600 px, posisi tengah, opacity 5–25%.
- Saat snapshot dibuat, gambar watermark dibekukan sebagai data image di JSON.
  Path privat tidak masuk snapshot dan perubahan template berikutnya tidak
  mengubah revisi lama.
