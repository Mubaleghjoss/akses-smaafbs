# Riwayat Literasi Numerasi

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
