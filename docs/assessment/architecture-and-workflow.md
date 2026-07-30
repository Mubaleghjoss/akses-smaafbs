# Arsitektur Data dan Workflow

## Kelompok tabel

| Kelompok | Tabel |
|---|---|
| Master terisolasi | `assessment_academic_years`, `assessment_semesters`, `assessment_subjects`, `assessment_teaching_assignments`, `assessment_homeroom_assignments` |
| Snapshot periode | `assessment_periods`, `assessment_period_rombels`, `assessment_period_students`, `assessment_period_assignments`, `assessment_period_homerooms` |
| Nilai | `assessment_schemes`, `assessment_components`, `assessment_scores`, `assessment_student_subject_results` |
| Wali kelas | `assessment_homeroom_reports` |
| Rapor/audit | `assessment_report_templates`, `assessment_report_snapshots`, `assessment_class_report_artifacts`, `assessment_report_share_links`, `assessment_audit_logs` |

Referensi ke `data_siswa`, `guru_tendik`, dan `rombels` tidak memakai cascade. Foreign key penuh hanya dipakai antartabel assessment. Penghapusan master lama tidak boleh menghapus nilai/snapshot historis.

## Status

Periode:

```text
draft -> open -> entry_closed -> verification -> locked -> published
```

Assignment:

```text
draft -> submitted -> verified -> locked
           |            |
           +-> returned <-+
                  |
                  +-> submitted
```

- Membuka periode membuat snapshot siswa, kelas, penugasan guru, wali kelas, dan cakupan skema dalam satu transaksi.
- Skema khusus kelas dipilih dari `source_rombel_id` ketika periode masih draf. Saat dibuka, resolver mencocokkannya dengan `assessment_period_rombels.source_rombel_id`; skema tidak bergantung pada snapshot yang belum terbentuk.
- Return dari tahap verifikasi membuka periode secara terkontrol agar assignment terpilih dapat diperbaiki; event dicatat pada audit.
- Reopen setelah lock/publish wajib menyertakan alasan dan assignment terpilih. Share link revisi lama dicabut, tetapi snapshot/PDF historis dipertahankan.
- Rekap wali kelas selalu memuat absensi, ekstrakurikuler, prestasi, dan catatan.
  Kolom status semester mengikuti `settings.collect_promotion_status`; jika
  belum dikonfigurasi, fallback aman adalah aktif untuk ASAS dan nonaktif untuk ASTS.

## Perhitungan

Satu kalkulator menangani semua nilai:

1. Validasi skor terhadap minimum/maksimum komponen.
2. Normalisasi skor ke skala 100.
3. Kalikan dengan bobot komponen.
4. Pastikan total bobot aktif tepat 100%.
5. Bulatkan memakai `PHP_ROUND_HALF_UP` dan precision skema.
6. Bandingkan hasil akhir dengan KKM skema (`settings.kkm`) dan simpan status
   ketuntasan di `calculation_detail`.
7. Simpan `calculation_detail`, `formula_version`, predikat, dan deskripsi.

Komponen wajib kosong membuat hasil belum lengkap dan menggagalkan submit. Deskripsi otomatis berasal dari domain terkuat/terlemah dan dapat dioverride guru.
`minimum_score` adalah batas input komponen, bukan KKM. KKM selalu dikonfigurasi
terpisah pada skala hasil akhir 0–100.

## Optimistic locking dan retry

- Satu assignment mempunyai `lock_version`.
- Browser mengirim versi yang dibaca bersama batch seluruh siswa.
- Save menaikkan versi di dalam row lock/transaction.
- Payload retry yang sudah persis tersimpan diterima secara idempoten.
- Payload berbeda dengan versi usang ditolak dan meminta pengguna memuat ulang.
- Local Storage hanya menjadi draf browser; database baru berubah setelah tombol **Simpan Draf**.

## Otorisasi

Role baru bersifat aditif: `super_admin`, `kurikulum`, `guru_mapel`, `wali_kelas`, `kepala_sekolah`.

- `admin` dan `guru_admin` menerima permission penuh sebagai alias super admin.
- `guru` menerima permission input/submit sebagai alias guru mapel.
- Identitas guru berasal dari `users.guru_tendik_id`.
- Scope record berasal dari snapshot teaching/homeroom, bukan label bebas pada user.
- Kepala sekolah hanya baca.
- Nilai akses modul `penilaian=none` menolak direct URL dan action server meskipun
  role masih memiliki permission granular.
- Kurikulum, kepala sekolah, dan wali kelas dalam scope dapat membuka matriks
  nilai baca-saja; hanya guru pengampu yang memenuhi Policy dapat mengedit.

Setiap action custom memeriksa permission dan Policy pada server. Menyembunyikan tombol bukan mekanisme keamanan.
