# Riwayat Literasi Numerasi

## 2026-08-06 - Protokol submit async-v2

- Menghentikan pola redirect `POST 302` yang diikuti HTML dan retry jawaban berulang.
- Menambahkan JSON khusus untuk validasi, verifikasi siswa, respons lama, dan respons di Sampah.
- Membatasi satu klik menjadi satu POST final dan maksimal satu replay setelah status tiket diperiksa.
- Menambahkan pemulihan Struk langsung dari tiket `completed` tanpa mengirim jawaban kembali.
- Menyatukan commit respons, jawaban, dan tiket selesai dalam satu transaksi dengan retry deadlock.
- Mengganti penguncian baris antrean global dengan cache lock nonblocking dan request key unik.
- Melonggarkan pagar IP sekolah, mempertahankan limiter per sesi/request, dan menambahkan trace ID.
- Mengganti scheduler command yang memerlukan `proc_open` dengan callback `Artisan::call()`.
- Menambahkan metrik penolakan, pemulihan Struk, throttle aplikasi, respons tak terduga, dan deadlock antrean.

## 2026-07-31 - Form nilai bersama

- Menyatukan form Detail/Nilai pada daftar responden materi dan History
  Pengerjaan Siswa melalui satu service grading.
- Menampilkan urutan Pertanyaan, Jawaban Siswa, Kunci Jawaban, status
  plagiasi/nilai otomatis, koreksi nilai, dan catatan guru.
- Kunci Esai ditampilkan sebagai teks; Benar/Salah sebagai tabel; Menjodohkan
  sebagai pasangan kiri ke kanan.
- History dengan materi yang hilang tetap dapat dibuka read-only tanpa error.
- Memperbaiki penggabungan header submit JSON dan menambahkan pemulihan Struk
  untuk respons HTTP sukses yang kosong/tidak lengkap.
- Pemulihan memakai tiket dan request ID yang sama, menyediakan tombol periksa
  ulang, serta mencatat diagnostik aman tanpa jawaban atau token.

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
# 2026-08-06

- Ambang indikasi kemiripan dinaikkan menjadi 80% dan setiap jawaban hanya menyimpan satu pembanding terdahulu terkuat.
- Jawaban yang sama dengan Kunci Jawaban resmi dikecualikan dari indikasi kemiripan.
- Batas minimal dan maksimal karakter menyesuaikan Kunci Jawaban secara aman.
- Ditambahkan dry-run/apply `literacy:similarity-reconcile` serta kartu ringkasan indikasi pada admin.
