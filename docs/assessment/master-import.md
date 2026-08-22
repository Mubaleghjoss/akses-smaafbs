# Workbook dan Impor Master

## Template resmi

Download dari halaman **Penilaian → Pengaturan Penilaian → Impor Master Resmi**. Workbook berisi:

- `PETUNJUK`
- `TAHUN_SEMESTER`
- `MAPEL`
- `PENUGASAN_GURU`
- `WALI_KELAS`
- `REF_GURU`
- `REF_ROMBEL`
- `REF_KATEGORI`

Kolom ID sistem pada sheet penugasan/referensi disembunyikan untuk mencegah perubahan tidak sengaja. Importer tetap dapat menyelesaikan referensi melalui nama yang unik. Jika nama guru ganda, ID sistem wajib tersedia.

## Kontrak kolom

`TAHUN_SEMESTER`:

```text
TAHUN_KODE, TAHUN_NAMA, TAHUN_MULAI, TAHUN_SELESAI,
SEMESTER_KODE, SEMESTER_NAMA, SEMESTER_MULAI, SEMESTER_SELESAI, AKTIF
```

`MAPEL`:

```text
KODE_MAPEL, NAMA_MAPEL, DESKRIPSI, KELOMPOK_KODE, KELOMPOK_NAMA,
URUTAN_KELOMPOK, URUTAN_MAPEL, AKTIF
```

Workbook lama dengan kolom `KODE_MAPEL, NAMA_MAPEL, DESKRIPSI, URUTAN,
AKTIF` tetap diterima. Mapel dari format lama masuk kelompok sementara
`BELUM / Belum Dikelompokkan` dan muncul sebagai pekerjaan wajib pada wizard
serta preflight rapor. Admin dapat memperbaikinya langsung melalui kartu
**Mapel, Kelompok, dan Urutan Rapor** tanpa mengunggah ulang seluruh workbook.

`PENUGASAN_GURU`:

```text
SEMESTER_KODE, MAPEL_KODE, NAMA_GURU, ID_GURU_SISTEM,
NAMA_ROMBEL, ID_ROMBEL_SISTEM, AKTIF, KATEGORI_KODE
```

`KATEGORI_KODE` menentukan kelompok rapor per assignment, sehingga satu mapel
dapat berstatus wajib pada satu kelas dan pilihan pada kelas lain. Workbook
lama tanpa kolom tersebut tetap diterima; importer memakai fallback kelompok
mapel lama ke `WAJIB`, `PILIHAN`, atau `UMUM-A-LEGACY`.

`WALI_KELAS`:

```text
SEMESTER_KODE, NAMA_GURU, ID_GURU_SISTEM,
NAMA_ROMBEL, ID_ROMBEL_SISTEM, AKTIF
```

Tanggal memakai `YYYY-MM-DD`; boolean memakai `YA` atau `TIDAK`.
Header transaksi wajib sama persis dan tetap pada urutan tersebut.
Importer menolak judul yang diubah, kolom yang hilang, kolom tambahan, atau
urutan yang dipindahkan. Rentang tanggal juga diperiksa: mulai tidak boleh
melewati selesai dan tanggal semester harus berada dalam tahun pelajaran.

## Preview dan apply

1. Upload disimpan sementara pada disk privat.
2. Preview memeriksa sheet/kolom, duplikat, referensi hilang, akun guru, dan status rombel.
3. Preview ditandatangani dengan HMAC aplikasi agar payload Livewire tidak dapat dimodifikasi.
4. Tombol **Terapkan Impor** hanya tersedia jika tidak ada error.
5. Apply menjalankan seluruh upsert dalam satu transaksi.
6. Baris yang hilang dari workbook tidak pernah dihapus/dinonaktifkan otomatis.
7. File upload sementara dihapus setelah apply berhasil.
8. Ringkasan apply masuk `assessment_audit_logs`.

Guru tanpa akun dan rombel nonaktif tampil sebagai warning; preflight pembukaan periode tetap menjadi pagar terakhir.
Importer tidak memberi atau mengganti role user. Setelah apply, admin wajib
memeriksa akun tertaut dan menetapkan role `guru_mapel`/`wali_kelas` sesuai
surat penugasan resmi sebelum membuka periode.

## Pengaturan langsung per guru

Untuk perubahan satu atau beberapa guru, admin tidak wajib mengunggah ulang
workbook:

1. Buka **Mapel Penilaian**, pilih semester, lalu gunakan **Atur Guru & Kelas**.
2. Pilih guru, beberapa kelas, dan kategori rapor per kelompok kelas.
3. Koreksi per guru tetap tersedia pada tab **Penilaian ASTS–ASAS** di **Guru
   & Tendik** dan membaca sumber assignment yang sama.
4. Jika guru menjadi wali kelas, tambah semester dan rombel pada kartu Wali
   Kelas.

Form langsung dan importer menulis tabel master terstruktur yang sama. Unique
index tetap mencegah penugasan ganda, snapshot nama diperbarui saat baris
disimpan, dan perubahan tercatat pada log Penilaian. Pengaturan langsung tidak
mengubah snapshot periode yang sudah dibuka.

## Dashboard ringkas

Dashboard Pengaturan Penilaian menampilkan kartu teks untuk pemilih periode,
menu konfigurasi, kesiapan fondasi, status pengumpulan, dan aktivitas terbaru.

Gunakan workbook untuk pembaruan massal. Gunakan halaman Mapel Penilaian dan
tab Penilaian pada Guru & Tendik untuk koreksi harian yang kecil.

Jika periode sudah dibuka sebelum kolom kelompok tersedia, pilih mapel yang
sudah diperbaiki lalu jalankan bulk **Terapkan Kelompok ke Periode Berjalan**.
Aksi eksplisit ini hanya menyentuh assignment periode yang belum
`locked/published`, tidak mengubah nilai, dan tidak menyentuh snapshot/PDF lama.

## Plotting versi kategori assignment

Halaman **Mapel Penilaian** menyediakan filter semester dan aksi **Atur Guru &
Kelas**. Satu guru dapat memegang banyak mapel/kelas, tetapi satu kombinasi
semester-mapel-kelas hanya boleh mempunyai satu guru aktif. Setiap kelompok
kelas memilih kategori rapor sendiri. Guru yang akunnya belum tertaut atau
belum mempunyai akses Input/Kirim Nilai ditandai belum siap dan tidak dapat
disimpan.

Aksi eksplisit **Terapkan ke Periode Terbuka** memindahkan plotting hanya pada
periode `open`. Perpindahan guru mempertahankan nilai dan menaikkan
`lock_version`; penghapusan hanya berlaku pada assignment Draf yang benar-benar
kosong. Assignment yang sudah dikirim, diverifikasi, dikunci, atau tidak aman
membatalkan seluruh transaksi. Snapshot dan PDF lama tidak berubah.

Dashboard Pengaturan Penilaian kini memakai kartu teks ringkas: pemilih periode,
menu konfigurasi termasuk Kategori Mapel, kesiapan fondasi, status pengumpulan,
dan aktivitas terbaru. Wizard lama yang menduplikasi resource tidak lagi
ditampilkan.

## Data awal matriks 2026/2027

`php artisan assessment:teaching-plan-2026` menjalankan preview tanpa menulis
database. Setelah seluruh nama guru, kelas, mapel, dan kategori cocok, jalankan
dengan `--apply`. Apply bersifat idempoten, memakai satu transaksi, mencatat
audit, dan menonaktifkan plotting lama yang bertentangan tanpa menghapusnya.
Nama guru dinormalisasi, tetapi hasil pencocokan wajib tepat satu Guru & Tendik
dengan akun tertaut. Command tidak menyentuh period assignment, nilai, snapshot,
atau PDF.
