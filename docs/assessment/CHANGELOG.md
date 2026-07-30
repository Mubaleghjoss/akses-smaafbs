# Riwayat Perubahan Penilaian

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
