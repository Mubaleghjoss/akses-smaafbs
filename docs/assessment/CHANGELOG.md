# Riwayat Perubahan Penilaian

## 2026-09-01

- Memusatkan peta halaman per jenis penilaian ke `App\Support\Assessment\AssessmentPageMap`.
  Sebelumnya tujuan tautan dipilih dengan pola dua cabang (`$type === ASTS ? A : B`)
  yang tersebar di hub, input nilai, status pengumpulan, rapor, notifikasi
  kegagalan, dan Mapel Penilaian. Akibatnya **ASAT selalu diarahkan ke halaman
  ASAS** tanpa pesan kesalahan: pengguna merasa membuka ASAT tetapi mengisi layar
  ASAS. Kini setiap jenis memakai halamannya sendiri, dan jenis baru cukup
  ditambahkan satu baris.
- Menampilkan **ASAT** sebagai baris menu tersendiri di induk Penilaian, sejajar
  ASTS dan ASAS, sekaligus mendaftarkan Setelan Awal Penilaian dan Matriks
  Penugasan yang sebelumnya sudah ada tetapi tidak dapat ditemukan dari sidebar.
- Menyeragamkan urutan menu Penilaian: Setelan Awal → ASTS → ASAS → ASAT →
  Matriks Penugasan → Pengaturan Penilaian, mengikuti pola urutan bertingkat
  sepuluh seperti kelompok menu lain.
- Menambahkan kartu **Pusat Penilaian** pada Pengaturan Penilaian yang dibangun
  dari enum jenis, sehingga jumlah periode dan semester yang diizinkan tiap jenis
  terlihat tanpa menambah kartu manual.
- Menyamakan label halaman rapor menjadi Cetak Rapor ASTS/ASAS/ASAT dan izin
  modul `penilaian` sekarang membuka halaman ASAT, Setelan Awal, serta Matriks
  Penugasan yang sebelumnya tertinggal.

## 2026-08-07

- Memisahkan scope **Input Nilai Saya** dari scope pemantauan wali kelas:
  dropdown, assignment awal, progres, dan kartu Input hanya memuat mapel guru
  pengampu.
- Menambahkan `mode=review` pada **Status → Tinjau Nilai** agar wali kelas tetap
  dapat memeriksa mapel lain di kelasnya dalam keadaan baca-saja.
- Menambahkan empty state untuk wali kelas tanpa mapel beserta tombol langsung
  ke Rekap Wali dan penjelasan cakupan yang responsif pada ASTS/ASAS.

## 2026-08-06

- Menyatukan konfigurasi 11 kolom rekap wali kelas ASTS/ASAS agar header,
  pilihan isi massal, jenis input, dan batas validasinya selalu konsisten.
- Mengosongkan nilai isi massal ketika pengguna mengganti kolom untuk mencegah
  angka, predikat, atau teks lama diterapkan ke jenis kolom yang berbeda.
- Menambahkan nama dan keterangan terstruktur untuk ekstrakurikuler/prestasi,
  editor per siswa, bulk tambah/ganti yang aman, serta hak edit kurikulum.
- Menjadikan kegagalan aksi ASTS/ASAS persisten dengan kendala, solusi, dan
  tombol perbaikan period-aware.
- Menormalkan nilai pada form/draft browser, menambahkan polling progres PDF
  saat run berjalan, dan menjaga teks ketidakhadiran seperti `0 hari` tetap
  berada dalam satu baris pada PDF.

## 2026-07-31

- Mengubah status Aktif template menjadi **Template Utama** dengan satu template
  utama per jenis ASTS/ASAS, aktivasi transaksional, status kelengkapan/kunci,
  riwayat penggunaan periode, detail read-only, preview, dan versi baru.
- Memisahkan **Siapkan Revisi** dari **Jadwalkan Kelas Terpilih**, memblokir
  revisi ganda, serta menambahkan mulai ulang audit-safe tanpa menghapus
  snapshot/PDF historis.
- Menambahkan template tiga halaman ASAS dengan bagian Status
  Semester/Kenaikan Kelas dan preflight sesuai konfigurasi periode.
- Menambahkan command rekonsiliasi dry-run/apply yang memasang template ASAS,
  memilih template utama, menghentikan revisi uji lama, dan opsional menyiapkan
  revisi berikutnya tanpa menjadwalkan job PDF.
- Menambahkan formatter tampilan nilai bersama: `99.9900` menjadi `99.99`,
  `85.5000` menjadi `85.5`, dan nilai bulat tidak memiliki nol desimal.
- Menambahkan kelompok dan urutan mapel pada master serta snapshot assignment,
  dengan kompatibilitas workbook lama melalui kelompok sementara `BELUM`.
- Menambahkan predikat/deskripsi sikap spiritual dan sosial serta bulk isi pada
  Rekap Wali Kelas.
- Menambahkan builder layout aman maksimal tiga halaman, draft template
  **SMA AFBS 3 Halaman**, posisi/ukuran watermark, preview data periode tanpa
  file permanen, serta preflight rapor resmi.
- Menambahkan wizard tujuh langkah Kelengkapan Data Rapor dan halaman koreksi
  langsung Mapel Penilaian tanpa menambah item sidebar.
- Memperbaiki kontras navigasi Penilaian terang/gelap dan menjaga tab tetap
  dapat digeser pada layar HP.
- Menambahkan bulk verifikasi dan pengembalian assignment secara atomik pada
  Status Pengumpulan, lengkap dengan modal alasan revisi.
- Menampilkan alasan, aktor, dan waktu revisi pada Input Nilai serta mengubah
  bulk nilai agar mode bawaan dapat menimpa data lama setelah konfirmasi.
- Mengganti job per siswa dengan pipeline per kelas yang memproses maksimal
  tiga siswa atau sekitar 40 detik per putaran, lalu membuat PDF gabungan.
- Menambahkan run pengendali, status `not_scheduled`/`cancelled`, lanjutkan kelas,
  penghentian khusus queue `assessment-reports`, serta rekonsiliasi dry-run CLI.
- Menyusun Cetak Rapor menjadi kartu bernomor, preview siswa nyata, progres
  kelas, bulk tautan langsung maksimal 50 siswa, dan watermark privat yang
  dibekukan ke snapshot.
- Menambah kontras terang/gelap dan perilaku mobile untuk kartu, pilihan kelas,
  progres, modal stop, serta daftar siswa.
- Mengubah setiap masalah Kelengkapan Data Rapor menjadi kartu aksi yang menuju
  halaman perbaikan tepat dengan periode dan tipe ASTS/ASAS tetap terpilih.
- Merapikan kartu Pengaturan dan Aktivitas Terbaru pada dashboard, serta Log
  Perubahan menjadi grid kartu responsif dengan tombol detail eksplisit.

## 2026-07-30

- Menambahkan fondasi terisolasi ASTS–ASAS, enum, model, factory, Policy, permission, dan template standar.
- Menambahkan workbook resmi dengan preview/HMAC dan apply transaksional.
- Menambahkan workflow periode/assignment, batch score dengan optimistic locking, kalkulator tunggal, serta snapshot ASTS untuk ASAS.
- Menambahkan dashboard, input desktop/mobile, status pengumpulan, rekap wali kelas, pengaturan, dan audit.
- Menambahkan snapshot rapor immutable, PDF privat individual/kelas, antrean prioritas rendah, checksum, retry, dan share link sementara.
- Feature flag awal tersedia untuk rollout dan rollback tanpa menghapus data.
- Memperbaiki konfigurasi skema per kelas agar dapat dipilih sebelum snapshot periode dibentuk.
- Menambahkan transaksi dan row lock pada perubahan skema, periode, dan template
  agar pembukaan periode/pembuatan rapor tidak beradu dengan form admin.
- Memperketat akses modul, ownership wali kelas, review nilai baca-saja, validasi
  header workbook, KKM eksplisit, detail audit, dan validasi input rekap.
- Memperketat revisi/publish/retry rapor, validasi checksum aktual, path unik,
  serta shared overlap lock agar job deploy lama dan baru tetap idempoten.
- Memindahkan Dashboard Penilaian, ASTS, ASAS, dan Pengaturan Penilaian ke
  kelompok navigasi **Manajemen Sekolah** agar tidak membuat kelompok menu
  terpisah.
- Memperbaiki kompilasi Blade pada rincian pratinjau Impor Master agar admin
  dapat membuka dan memeriksa hasil workbook tanpa error 500.
- Merapikan navigasi menjadi satu induk **Penilaian** di dalam Manajemen
  Sekolah dengan tiga halaman hub: Pengaturan Penilaian, ASTS, dan ASAS.
  Halaman rinci tetap tersedia melalui kartu responsif dan tidak lagi memenuhi
  sidebar.
- Mengubah ringkasan pengaturan serta pekerjaan ASTS/ASAS menjadi kartu
  responsif dengan ikon, status, jumlah data, dan tombol tindakan yang aman
  pada layar HP.
- Menambahkan alur penyiapan enam langkah bergaya kartu Materi Boarding pada
  Pengaturan Penilaian. Setiap kartu membaca kesiapan guru/akun, rombel/siswa,
  penugasan terstruktur, periode, bobot skema, dan status pembukaan secara
  langsung dari data.
- Menambahkan petunjuk eksplisit bahwa label mapel pada profil guru tidak
  menggantikan penugasan resmi guru–mapel–kelas–semester.
- Memperjelas Dashboard Penilaian dan Impor Master menggunakan kartu visual
  dengan CSS panel Filament yang eksplisit, sehingga warna, batas, ikon, tombol,
  dan susunan responsif tetap tampil walaupun halaman admin tidak memakai
  bundle Tailwind publik.
- Menambahkan alur visual empat tahap pada Impor Master serta kartu hasil data
  tahun/semester, mapel, guru pengampu, kelas, dan wali kelas.
- Menambahkan panduan maksud, tujuan, hasil, contoh ASTS/ASAS, pilihan cakupan,
  dan indikator langsung total bobot pada form Komponen dan Bobot. Status siap
  hanya muncul ketika total komponen aktif tepat 100%.
- Memperbaiki validasi pembuatan dan perubahan skema agar membaca komponen dari
  raw state repeater relasi Filament. Satu atau beberapa komponen yang sudah
  terlihat pada form tidak lagi keliru dianggap kosong saat disimpan.
- Mengintegrasikan penugasan guru mapel, kelas mengajar, dan wali kelas ke
  halaman Guru & Tendik melalui tab Penilaian ASTS–ASAS. Form langsung memakai
  tabel master terstruktur yang sama dengan Impor Master, memperbarui snapshot
  nama, memeriksa duplikasi, menjalankan Policy, dan menulis audit log.
- Menambahkan ringkasan penugasan dan aksi Atur Mapel & Walas pada daftar guru,
  serta kartu pintas Guru Mapel dan Wali Kelas pada Pengaturan Penilaian.
- Memberi CSS panel eksplisit pada card Pengaturan, kesiapan fondasi, status
  pengumpulan, aktivitas, serta hub ASTS/ASAS agar tidak jatuh menjadi teks
  polos dan tetap responsif pada layar HP.
- Memperbaiki direct URL Status Pengumpulan yang sebelumnya mengirim relasi
  `HasMany` ke scope bertipe `Builder` dan dapat menghasilkan error 500.
- Mengganti keterangan progres yang ambigu menjadi jumlah dikirim dan belum
  dikirim; penugasan Draf/Dikembalikan diprioritaskan agar guru dapat
  melanjutkan kelas lain setelah satu kelas dikirim atau diverifikasi.
- Menambahkan header navigasi responsif pada seluruh halaman kerja ASTS/ASAS,
  kartu Cakupan Saya dari snapshot mapel/wali kelas, dan akses rekap untuk wali
  kelas yang benar-benar tercatat pada snapshot periode.
- Menambahkan pengisian massal nilai dan deskripsi berbasis pilihan siswa.
  Perubahan tetap masuk state/draf browser dahulu dan baru tersimpan melalui
  Simpan Draf agar optimistic locking dan batch assignment tidak dilewati.
- Menambahkan bulk action Rekap Wali Kelas untuk absensi, ekstrakurikuler,
  prestasi, catatan wali, dan status semester tanpa melewati tombol Simpan
  Rekap maupun Policy.
- Menyederhanakan header halaman kerja menjadi satu tombol kembali dan satu
  baris tab, serta menghapus kartu cakupan/keterangan yang mengulang isi pada
  halaman Status, Rekap Wali, dan Rapor.
- Memberi CSS panel eksplisit pada filter, tabel, kartu siswa, bulk action,
  empty state, dan save bar Status/Rekap Wali agar kontras mode terang/gelap
  tetap terbaca tanpa bergantung pada utility Tailwind.
