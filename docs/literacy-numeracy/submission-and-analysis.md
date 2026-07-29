# Submit, Antrean, dan Analisis

## Alur

1. Browser menyimpan draf di `sessionStorage`.
2. Browser meminta tiket antrean.
3. Tiket `admitted` memberi izin submit akhir.
4. Controller memvalidasi ulang seluruh payload terhadap pertanyaan di database.
5. Respons dan jawaban disimpan dalam transaksi.
6. Tiket ditandai selesai dan slot dilepas dalam `finally`.
7. Job kemiripan dijadwalkan; halaman sukses tidak menunggu analisis.

Textarea, radio Benar/Salah, dan dropdown Menjodohkan ikut draf. FormData tetap kecil karena tidak ada file audio.

## Status submit

- `OK-LANGSUNG`: tidak menunggu antrean.
- `Q-ANTRE`: sempat menunggu tiket.
- `R-429`, `R-503`, `R-RETRY`: browser melakukan retry sebelum berhasil.
- `LEGACY`: respons dibuat sebelum pencatatan status.

Retry memakai request ID dan tiket yang sama agar koneksi putus tidak membuat respons ganda.

## Analisis

- Queue: `literacy-analysis`.
- Hanya jawaban Esai dengan deteksi aktif yang dibandingkan.
- Benar/Salah dan Menjodohkan dilewati seluruhnya.
- Aksi admin analisa ulang hanya menjadwalkan job.
- Versi analisis mencegah hasil edit lama menimpa jawaban terbaru.

## Aturan perubahan

- Jangan menambah `sleep()` pada request publik.
- Jangan mengunggah rekaman suara.
- Jangan menjalankan `similar_text()` di controller submit.
- Jangan menghapus unique key respons siswa tanpa mengganti perlindungan duplikasinya.
- Validasi browser hanya bantuan; validasi server tetap wajib.
