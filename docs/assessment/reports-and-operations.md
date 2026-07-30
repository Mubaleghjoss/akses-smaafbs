# Rapor, Antrean, dan Keamanan

## Snapshot dan PDF

- Periode harus `locked` atau `published`.
- Template harus sama tipe dengan periode.
- Pembuatan revisi wajib alasan.
- Snapshot JSON membekukan identitas, hasil mapel, wali kelas, template, tanda tangan, dan formula.
- Job membaca snapshot, bukan tabel live.
- PDF siswa dan gabungan kelas disimpan pada disk `local`, di bawah `storage/app/private/assessment-reports`.
- Penyimpanan atomik menghasilkan checksum SHA-256.
- Status: `pending`, `processing`, `completed`, `failed`.
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

## Download panel

- Controller memanggil Policy.
- File divalidasi ulang lewat status dan checksum.
- Respons memakai `private, no-store`, `nosniff`, dan `noindex`.
- Download dicatat pada audit.
- PDF gabungan kelas hanya tersedia melalui panel.

## Share link

- Hanya rapor individual pada periode `published`.
- Token acak 32 byte; database hanya menyimpan SHA-256 token.
- Masa berlaku: 1, 3, atau 7 hari (default UI 1 hari/24 jam).
- Token dapat dicabut, terkena rate limit, dan setiap download diaudit.
- Reopen/regenerate mencabut link revisi lama.
- Link hanya dapat dibuat dan dipakai untuk pasangan template-revisi terbaru
  yang tercatat sebagai set published.
- Token plaintext hanya ditampilkan saat dibuat; tidak bisa dipulihkan dari database.

## Retry

Gunakan tombol **Coba Lagi** hanya pada record `failed`. Sistem mengubah status ke `pending` dan mengirim job yang sama. Jangan menghapus record snapshot/artifact untuk mengulang.

Retry revisi historis atau PDF yang sudah completed ditolak oleh server,
meskipun method Livewire dipanggil secara langsung.
