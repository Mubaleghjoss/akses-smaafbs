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
NAMA_ROMBEL, ID_ROMBEL_SISTEM, AKTIF
```

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

1. Buka **Guru & Tendik**.
2. Pilih **Atur Mapel & Walas** pada guru.
3. Pada tab **Penilaian ASTS–ASAS**, tambah pasangan semester, mapel, dan kelas.
4. Jika guru menjadi wali kelas, tambah semester dan rombel pada kartu Wali
   Kelas.

Form langsung dan importer menulis tabel master terstruktur yang sama. Unique
index tetap mencegah penugasan ganda, snapshot nama diperbarui saat baris
disimpan, dan perubahan tercatat pada log Penilaian. Pengaturan langsung tidak
mengubah snapshot periode yang sudah dibuka.

## Wizard kelengkapan rapor

Dashboard Pengaturan Penilaian menyediakan tujuh pintasan yang membaca kondisi
database, bukan checklist manual:

1. identitas sekolah dan penanda tangan;
2. tahun pelajaran dan semester;
3. mapel, kelompok, dan urutan rapor;
4. guru-mapel-kelas;
5. wali kelas;
6. siswa, nilai, data wali kelas, dan periode;
7. layout, watermark, serta preflight.

Gunakan workbook untuk pembaruan massal. Gunakan halaman Mapel Penilaian dan
tab Penilaian pada Guru & Tendik untuk koreksi harian yang kecil.

Jika periode sudah dibuka sebelum kolom kelompok tersedia, pilih mapel yang
sudah diperbaiki lalu jalankan bulk **Terapkan Kelompok ke Periode Berjalan**.
Aksi eksplisit ini hanya menyentuh assignment periode yang belum
`locked/published`, tidak mengubah nilai, dan tidak menyentuh snapshot/PDF lama.
