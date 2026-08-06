# Rapor, Antrean, dan Keamanan

## Snapshot dan PDF

- Periode harus `locked` atau `published`.
- Template harus sama tipe dengan periode.
- Hanya satu template yang berstatus **Template Utama** untuk setiap jenis
  ASTS/ASAS. Aktivasi template baru mengarsipkan template utama lama secara
  transaksional; snapshot dan PDF historis tidak berubah.
- Template belum lengkap atau bertanggal berlaku di masa depan tidak dapat
  dijadikan utama.
- Pembuatan revisi wajib alasan.
- Snapshot JSON membekukan identitas, hasil mapel, wali kelas, template, tanda tangan, dan formula.
- Job membaca snapshot, bukan tabel live.
- Snapshot baru memakai mode `stream`: PDF siswa dirender saat diunduh dan tidak disimpan permanen. Snapshot JSON immutable memiliki checksum SHA-256 sebagai bukti integritas.
- PDF historis mode `stored` tetap dibaca dari disk privat. PDF gabungan kelas baru menjadi cache privat 24 jam di `storage/app/private/assessment-reports`.
- `php artisan app:storage-audit` memisahkan ukuran PDF, aset build, impor, temporary upload, backup media, dan file rapor yatim. `app:storage-maintain --apply` hanya menyentuh target allowlist.
- File PDF rapor yang tidak lagi dirujuk database dipindahkan dahulu ke `storage/app/private/orphan-quarantine`; scheduler baru menghapus isi karantina setelah berumur minimal tujuh hari.
- Status PDF: `not_scheduled`, `pending`, `processing`, `completed`, `failed`,
  dan `cancelled`.
- Satu `assessment_report_generation_runs` mengendalikan progres untuk pasangan
  periode-template-revisi tanpa mengubah snapshot immutable.
- Job memakai `WithoutOverlapping`, retry/backoff, dan aman dijalankan ulang.
- Lock job dibagi antara nama job legacy dan canonical agar payload antrean dari
  deploy sebelumnya tidak dapat menulis PDF yang sama secara paralel.
- Publish menyimpan pasangan template dan revisi aktif pada settings periode serta memvalidasi kelengkapan dan checksum snapshot. Cache PDF gabungan kelas bukan syarat publish.
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

1. Aksi **Siapkan Revisi** membekukan snapshot seluruh siswa, memberi status
   awal `ready`, dan tidak membuat job apa pun.
2. Admin memilih kelas lalu menjalankan **Jadwalkan Kelas Terpilih**. Sistem mengirim sekitar satu
   `GenerateClassReportPipeline` untuk setiap kelas aktif.
3. Job memvalidasi seluruh checksum snapshot kelas lalu membuat tepat satu PDF gabungan kelas.
4. Cache kelas berlaku sesuai `ASSESSMENT_REPORT_CLASS_CACHE_HOURS` (default 24 jam) dan dibersihkan scheduler setiap jam.
5. **Buat Ulang Cache** memakai revisi yang sama setelah cache gagal atau kedaluwarsa; snapshot siswa tidak dibuat ulang.

Panel **Progres / Cache PDF per kelas** melakukan polling setiap lima detik hanya
ketika revisi berstatus `running` dan panel terlihat. Polling berhenti otomatis
pada status terminal agar tidak menambah beban shared hosting.

Sistem menolak revisi baru selama masih ada run `prepared` atau `running`.
Gunakan **Mulai Ulang dengan Revisi Baru**, isi alasan, lalu sistem membatalkan
run lama secara audit-safe dan menyiapkan revisi berikutnya tanpa job.
Membuka kembali periode untuk koreksi nilai juga membatalkan run rapor terbuka
dengan alasan koreksi yang sama, sehingga job lama tidak dapat merender data
sebelum koreksi.

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
- Link merender PDF siswa dari snapshot melalui slot render database dan tidak membuat file permanen.
- Link hanya dapat dibuat dan dipakai untuk pasangan template-revisi terbaru
  yang tercatat sebagai set published.
- Token plaintext hanya ditampilkan saat dibuat; tidak bisa dipulihkan dari database.

## Retry

Gunakan tombol **Coba Lagi** hanya pada record `failed`. Sistem mengubah status
kelas/snapshot ke `pending` dan melanjutkan pipeline kelas. Jangan menghapus
record snapshot/artifact untuk mengulang.

Retry revisi historis atau PDF yang sudah completed ditolak oleh server,
meskipun method Livewire dipanggil secara langsung.

## Layout aman, preview, dan watermark

- Template layout versi 2 hanya menerima daftar section terstruktur. Tidak ada
  input HTML, Blade, CSS, atau PHP bebas dari admin.
- Bagian dapat diaktifkan, diurutkan, dan ditempatkan pada halaman 1-3.
  Identitas siswa, minimal satu bagian akademik, dan tanda tangan wajib ada.
- Template draft **SMA AFBS 3 Halaman** membagi isi menjadi sikap/ringkasan,
  capaian kompetensi, lalu ekstrakurikuler-prestasi-absensi-catatan-tanggapan
  orang tua-tanda tangan.
- Template ASAS tiga halaman menambahkan bagian
  **Status Semester/Kenaikan Kelas** pada halaman 3. Bagian ini dirender dan
  diperiksa preflight hanya ketika periode mengaktifkan
  `settings.collect_promotion_status`.

- Preview memakai satu siswa nyata dari periode, dirender sinkron tanpa
  snapshot/job/file permanen, diberi label **Pratinjau—bukan rapor resmi**,
  rate limit, Policy, `no-store`, dan `noindex`.
- Watermark template hanya menerima PNG/JPEG/WebP pada disk `local` privat,
  dioptimalkan GD menjadi PNG maksimal 1600 px, posisi tengah, opacity 5–25%.
- Saat snapshot dibuat, gambar watermark dibekukan sebagai data image di JSON.
  Path privat tidak masuk snapshot dan perubahan template berikutnya tidak
  mengubah revisi lama.

Preview tetap tersedia ketika data belum lengkap dan diberi penanda
**PRATINJAU - DATA BELUM LENGKAP**. Watermark mendukung posisi
atas/tengah/bawah, lebar 20-90%, dan opacity 5-25%.

## Preflight rapor resmi

Generate ditolak sebelum membuat snapshot/job jika kelas terpilih masih
memiliki mapel tanpa kelompok, assignment belum terkunci, wali kelas hilang,
nilai akhir siswa kosong, sikap wajib kosong, atau identitas template belum
lengkap. Hasil ditampilkan sebagai kartu per kelompok dengan jumlah dan sampel
kelas, mapel, atau siswa. Preview tidak melewati pagar ini karena selalu
berlabel bukan dokumen resmi.

Setiap masalah preflight memiliki aksi perbaikan yang period-aware:
**Kelola Mapel**, **Atur Guru Mapel**, **Atur Wali Kelas**, **Buka Input
Nilai**, **Buka Status Pengumpulan**, **Buka Rekap Wali**, atau **Ubah Template
Rapor**. Tautan ASTS/ASAS selalu membawa ID periode aktif. Pengguna tanpa izin
tetap melihat masalahnya, tetapi tidak memperoleh tombol tindakan yang tidak
diizinkan.

Nilai yang tampil pada Input Nilai, preview, rekap, dan PDF dibatasi maksimal
dua desimal dan nol berlebih dibuang. Nilai database `decimal:4`, formula, serta
detail perhitungan tidak diubah.

## Rekap wali kelas

- Tabel memiliki kolom Siswa serta sepuluh kolom rekap wajib: Sakit, Izin,
  Alpa, Predikat/Deskripsi Spiritual, Predikat/Deskripsi Sosial,
  Ekstrakurikuler, Prestasi, dan Catatan Wali. Status Semester tetap
  kondisional untuk ASAS.
- Isi massal hanya mengubah state formulir siswa yang dicentang. Database baru
  berubah setelah **Simpan Rekap Wali Kelas**. Mode isi-kosong aktif secara
  bawaan dan angka absensi `0` dianggap kosong.
- Ekstrakurikuler dan prestasi disimpan pada JSON yang sudah ada sebagai daftar
  `{name, description}`. Nama wajib, keterangan opsional. Pengisian massal
  menambahkan poin tanpa duplikat secara bawaan; penggantian seluruh daftar
  memerlukan pilihan dan konfirmasi eksplisit.
- Admin, kurikulum/pemegang `penilaian.verify`, dan wali kelas pemilik rombel
  dapat mengubah rekap pada status yang diizinkan. Guru lain tetap hanya
  memperoleh cakupan sesuai policy.
- Data JSON lama yang hanya mempunyai `description` tetap dipertahankan dan
  ditampilkan dengan nama kosong untuk dilengkapi. Snapshot lama tidak diubah;
  setelah memperbaiki rekap, buat revisi baru agar PDF berikutnya memakai data
  terbaru.

## Notifikasi kegagalan aksi

Kegagalan aksi ASTS/ASAS memakai notifikasi persisten berisi **Kendala**,
**Solusi**, dan tombol menuju halaman perbaikan yang membawa periode aktif.
Notifikasi hanya hilang setelah tombol tutup dipilih atau halaman dimuat ulang.
Tombol tidak diberikan ke halaman yang tidak dapat diakses pengguna; fallback
mengarah ke pusat/pengaturan penilaian yang berwenang.

## Rekonsiliasi template produksi

Command ini tidak pernah menjadwalkan job PDF. Jalankan dry-run dahulu:

```bash
php artisan assessment:reconcile-report-templates --period=1 --cancel-open --prepare-new
```

Sesudah backup database dan private report storage, terapkan dengan akun admin
yang sah:

```bash
php artisan assessment:reconcile-report-templates --apply --actor=1 \
  --period=1 --cancel-open --prepare-new
```

Hasilnya memasang template standar dan tiga halaman ASTS/ASAS yang belum ada,
menjadikan tiga halaman sebagai template utama, menyalin identitas bersama dari
ASTS ke ASAS, menghentikan revisi terbuka yang digantikan, dan menyiapkan revisi
berikutnya. Admin tetap harus memilih kelas pilot melalui UI sebelum antrean PDF
berisi job.
