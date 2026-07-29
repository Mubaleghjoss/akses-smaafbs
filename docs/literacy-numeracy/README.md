# Literasi Numerasi

Dokumen ini adalah pintu masuk untuk pengembang dan AI yang mengubah modul Literasi Numerasi.

## Urutan baca

1. [Jenis pertanyaan dan penilaian](question-types.md)
2. [Submit, antrean, retry, dan analisis](submission-and-analysis.md)
3. [Monitor jaringan sekolah](school-network-monitor.md)
4. [Runbook insiden](incident-runbook.md)
5. [Riwayat perubahan](CHANGELOG.md)

## Batas penting

- URL publik utama adalah `/perpustakaan/program-literasi-numerasi/{slug}`.
- Pertanyaan dikunci setelah materi memiliki responden.
- Satu siswa hanya memiliki satu respons aktif per materi. Respons di Sampah masih mempertahankan unique key.
- Submit dan edit memakai tiket antrean yang idempoten. Jangan memindahkan analisis kemiripan kembali ke request submit.
- Deteksi kemiripan hanya untuk Esai. Jawaban objektif memang dapat sama dan tidak boleh disebut plagiasi.
- Setiap perubahan admin harus usable pada HP sesuai `AGENTS.md`.

## Peta kode

- Model: `PerpustakaanLiterasiMaterial`, `PerpustakaanLiterasiQuestion`, `PerpustakaanLiterasiResponse`, dan `PerpustakaanLiterasiAnswer`.
- Submit publik: `PerpustakaanLiteracyProgramController`.
- Antrean: `LiteracySubmissionQueue`.
- Analisis background: `AnalyzeLiteracyResponseSimilarity` dan `LiterasiSimilarityAnalyzer`.
- Admin: `PerpustakaanLiterasiMaterialResource` beserta relation manager responden.
- UI publik: `resources/views/library/literacy`.

Jangan menyimpan token monitor, kredensial server, data pribadi siswa, atau isi `.env` di dokumentasi.
