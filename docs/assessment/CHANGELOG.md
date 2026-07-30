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
