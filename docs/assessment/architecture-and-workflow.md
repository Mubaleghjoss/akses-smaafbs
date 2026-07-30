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

## Alur penyiapan dari admin

Halaman **Pengaturan Penilaian** menampilkan enam kartu bernomor yang harus
diikuti berurutan:

1. Periksa identitas guru dan tautan akun login pada **Guru & Tendik**.
2. Periksa rombel aktif dan siswa aktif per kelas.
3. Siapkan tahun, semester, dan mata pelajaran melalui **Impor Master Resmi**.
   Penugasan guru–mapel–kelas dan wali kelas dapat diterapkan massal dari
   workbook atau dikelola per guru melalui **Guru & Tendik → Penilaian
   ASTS–ASAS**.
4. Buat periode ASTS atau ASAS dan pilih rombel peserta.
5. Buat skema serta komponen dengan total bobot aktif tepat 100%.
6. Jalankan preflight melalui aksi **Buka Periode**.

`users.guru_mapel_label` tetap hanya label tampilan lama dan tidak menjadi
sumber transaksi. Pengaturan baru pada tab **Penilaian ASTS–ASAS** di halaman
Guru & Tendik menulis langsung ke `assessment_teaching_assignments` dan
`assessment_homeroom_assignments`, sama dengan target data Impor Master.
Penugasan transaksi selalu berasal dari pasangan terstruktur semester, guru,
mata pelajaran, dan rombel.

Perubahan master melalui Guru & Tendik hanya memengaruhi periode yang dibuka
setelah perubahan. Snapshot periode yang sudah dibuka tetap immutable.

Saat periode dibuka, siswa diambil dari `data_siswa` yang berstatus aktif dan
`rombel_saat_ini`-nya sama dengan nama rombel aktif yang dipilih. Guru pengampu
dan wali kelas wajib memiliki akun `users.guru_tendik_id` yang tertaut sebelum
snapshot dapat dibuat.

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
- Snapshot `assessment_period_homerooms` menjadi bukti akses record wali kelas.
  Akun yang tertaut ke guru pada snapshot dapat membuka dan mengisi rekap
  kelasnya sendiri walaupun akun masih memakai role umum `guru`; akses modul
  `penilaian` tetap wajib dan kelas lain tetap ditolak oleh Policy.
- Setiap halaman kerja ASTS/ASAS menampilkan kartu **Cakupan Saya** yang membaca
  mapel, kelas mengajar, dan wali kelas langsung dari snapshot periode.

## Pengisian nilai massal

- Penugasan `draft` dan `returned` selalu diprioritaskan di pilihan Mapel dan
  Kelas. Penugasan `submitted`, `verified`, dan `locked` tetap dapat ditinjau
  tetapi tidak dibuka kembali secara diam-diam.
- Guru dapat mencentang siswa lalu menerapkan satu nilai komponen dan/atau satu
  deskripsi ke banyak siswa. Opsi aman bawaan hanya mengisi kolom kosong.
- Aksi massal hanya mengubah state formulir dan draf browser. Database tetap
  berubah melalui tombol **Simpan Draf** sehingga batch satu assignment dan
  `lock_version` tetap berlaku.
- Mengirim atau memverifikasi satu assignment tidak menutup assignment lain
  pada periode yang sama. Status kemajuan selalu ditulis sebagai jumlah
  dikirim dan jumlah yang masih belum dikirim.

## Pengisian rekap wali kelas massal

- Wali kelas dapat memilih beberapa siswa lalu mengisi massal salah satu kolom:
  Sakit, Izin, Alpa, Ekstrakurikuler, Prestasi, Catatan Wali, dan Status
  Semester ketika periode mengaktifkannya.
- Opsi aman bawaan hanya mengisi data kosong. Untuk kolom ketidakhadiran, angka
  nol diperlakukan sebagai data kosong.
- Aksi massal hanya mengubah state formulir. Penyimpanan database tetap melalui
  tombol **Simpan Rekap Wali Kelas**, Policy record, dan transaction yang sama
  dengan pengisian satu per satu.

Setiap action custom memeriksa permission dan Policy pada server. Menyembunyikan tombol bukan mekanisme keamanan.
