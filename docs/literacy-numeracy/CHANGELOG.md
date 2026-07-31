# Riwayat Literasi Numerasi

## 2026-07-31 - Form nilai bersama

- Menyatukan form Detail/Nilai pada daftar responden materi dan History
  Pengerjaan Siswa melalui satu service grading.
- Menampilkan urutan Pertanyaan, Jawaban Siswa, Kunci Jawaban, status
  plagiasi/nilai otomatis, koreksi nilai, dan catatan guru.
- Kunci Esai ditampilkan sebagai teks; Benar/Salah sebagai tabel; Menjodohkan
  sebagai pasangan kiri ke kanan.
- History dengan materi yang hilang tetap dapat dibuka read-only tanpa error.

## 2026-07-30 - Struk aman, direct link, dan dispensasi

- Menambahkan daftar status teman satu kelas pada Struk Pengiriman dan pesan Amal Salih untuk mengingatkan teman yang belum mengisi.
- Menambahkan salin daftar WhatsApp pada Status Pengisian Materi, dikelompokkan per kelas dengan kode `[SAKIT]` dan `[TES MT]`.
- Memastikan JavaScript fallback admin ikut disinkronkan ke document root cPanel agar tombol clipboard aktif di produksi.
- Menggunakan hash isi file sebagai versi JavaScript admin agar browser tidak mempertahankan skrip clipboard lama.
- Membedakan pesan retry koneksi, 429, timeout, 500, dan 503 agar gangguan jaringan tidak keliru disebut antrean penuh.
- Menambahkan patokan status di panel murid: draf lokal, percobaan otomatis, serta struk dan kode edit sebagai bukti konfirmasi penyimpanan.
- Submit dan edit berhasil menuju struk session tanpa soal/jawaban dengan cache `no-store` dan navigasi `location.replace()`.
- Materi nonaktif atau melewati waktu tutup tetap dapat dibuka dan dikirim melalui direct link, tetapi tetap hilang dari daftar.
- Materi sebelum waktu buka menampilkan halaman ramah tanpa bacaan, pertanyaan, tiket, atau form.
- Sensor `window.blur` dan indikator Percobaan Keluar dihapus; hanya halaman tersembunyi lebih dari 10 detik yang dicatat.
- Menambahkan dispensasi Sakit/Tes MT dengan izin admin, soft delete, pembatalan otomatis saat submit nyata, dan rincian jawaban + dispensasi pada metrik partisipasi.
- Statistik akademik dan plagiasi tetap hanya memakai respons nyata.

## 2026-07-29 - Editor tabel dan garis Menjodohkan

- Form admin Benar/Salah dan Menjodohkan diubah menjadi tabel dua kolom yang responsif.
- Konfigurasi Menjodohkan lama tetap kompatibel melalui konversi form dua arah dengan ID pasangan stabil.
- Frontend Menjodohkan menampilkan koneksi SVG berpanah pada layar lebar dan fallback dropdown pada HP/no-JS.
- Koneksi SVG hanya bekerja di browser dan tidak menambah request maupun beban antrean submit.

## 2026-07-29

- Menambahkan tipe Esai, tabel Benar/Salah, dan Menjodohkan.
- Menambahkan skor per butir, payload jawaban terstruktur, serta koreksi poin admin.
- Menambahkan dikte Bahasa Indonesia tanpa penyimpanan audio.
- Memastikan analisis kemiripan hanya berjalan untuk Esai.
- Memperluas draf dan antrean agar mendukung radio serta dropdown.
- Mengganti monitor Windows dengan launcher tanpa konsol dan kontrol desktop aktif/nonaktif/status.
- Menambahkan status agent monitor pada Ringkasan Literasi.

## 2026-07-28

- Menambahkan monitor DNS/TCP/HTTPS dari jaringan sekolah.
- Menambahkan ringkasan operasional antrean, retry, worker, cron, dan jaringan.

## 2026-07-23

- Menambahkan status submit langsung, antre, retry 429, retry 503, dan legacy pada responden.
- Menambahkan thumbnail sosial materi untuk WhatsApp.

## 2026-07-21

- Menambahkan antrean submit idempoten dan memindahkan analisis kemiripan ke background.
