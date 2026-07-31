# Submit, Antrean, dan Analisis

## Alur

1. Browser menyimpan draf di `sessionStorage`.
2. Browser meminta tiket antrean.
3. Tiket `admitted` memberi izin submit akhir.
4. Controller memvalidasi ulang seluruh payload terhadap pertanyaan di database.
5. Respons dan jawaban disimpan dalam transaksi.
6. Tiket ditandai selesai dan slot dilepas dalam `finally`.
7. Job kemiripan dijadwalkan; halaman sukses tidak menunggu analisis.
8. Browser berpindah dengan `location.replace()` ke struk session tanpa soal dan jawaban.

Textarea, radio Benar/Salah, dan dropdown Menjodohkan ikut draf. FormData tetap kecil karena tidak ada file audio.

## Status submit

- `OK-LANGSUNG`: tidak menunggu antrean.
- `Q-ANTRE`: sempat menunggu tiket.
- `R-429`, `R-503`, `R-RETRY`: browser melakukan retry sebelum berhasil.
- `LEGACY`: respons dibuat sebelum pencatatan status.

Retry memakai request ID dan tiket yang sama agar koneksi putus tidak membuat respons ganda.

### Pesan status untuk murid

- Pesan retry dibedakan berdasarkan penyebab: koneksi perangkat, HTTP 429, timeout 408/504, layanan 502/503, atau gangguan aplikasi 500.
- Hanya status tiket `waiting` yang memakai kalimat **sudah masuk antrean** dan menampilkan posisi. Gangguan koneksi tidak boleh disebut antrean penuh.
- Selama retry, isian tetap berada di form dan disalin ke `sessionStorage`; murid tidak perlu menekan Kirim berulang dan harus mempertahankan tab tetap terbuka.
- Draf bukan bukti respons sudah tersimpan di server. Bukti konfirmasi yang mudah dipahami murid adalah halaman **Struk Pengiriman** beserta kode edit.
- Jika retry otomatis habis, murid diminta memeriksa koneksi lalu menekan Kirim satu kali. Request ID yang sama menjaga retry tetap idempoten.
- Respons sukses yang tidak lengkap, misalnya HTTP 2xx berisi HTML, JSON kosong,
  atau JSON tanpa `redirect_url`, tidak langsung dianggap gagal. Browser memakai
  tiket dan `submission_request_id` yang sama untuk memeriksa status dan
  memulihkan flash session Struk tanpa membuat respons kedua.
- Saat Struk belum dapat dibuka, panel pemulihan menyediakan **Periksa Status
  Lagi** dan **Kembali Perbaiki Jawaban**. Menutup panel tidak membatalkan tiket
  yang sudah `processing` atau `completed`.
- Event `unexpected_success_payload` hanya mencatat status HTTP, content type,
  status tiket, dan waktu. Isi jawaban, token, serta payload mentah tidak boleh
  masuk log diagnostik.

## Struk aman dan cache

- Submit baru maupun edit menuju `/perpustakaan/program-literasi-numerasi/selesai`.
- Struk hanya membaca flash session: identitas, materi, waktu, status submit, dan kode edit.
- Struk tidak memuat bacaan, pertanyaan, jawaban, atau tombol edit langsung.
- Struk memuat status partisipasi teman **satu kelas**: sudah mengisi, belum mengisi, dan dispensasi. Data diambil setelah redirect agar tidak memperpanjang slot submit.
- Daftar teman hanya menampilkan nama, kelas, dan label dispensasi; jawaban, nilai, kode edit teman, serta status internal Sampah tidak pernah ditampilkan.
- Pesan Amal Salih mengajak murid mengingatkan teman yang belum mengisi secara sopan tanpa membagikan soal atau jawaban.
- Form publik, form edit, dan struk memakai `Cache-Control: no-store`.
- JavaScript menghapus draf setelah sukses dan memakai `location.replace()` agar perangkat bersama tidak mudah kembali ke jawaban sebelumnya.

## Aturan direct link

- Daftar publik tetap memakai scope `availableForPublic()`.
- Resolver direct link hanya menolak materi soft-deleted.
- Materi nonaktif atau melewati `closes_at` tetap membuka form dan menerima tiket/submit.
- Materi sebelum `opens_at` menampilkan halaman HTTP 200 tanpa bacaan/pertanyaan/form; endpoint tiket dan submit menolak dengan validasi.
- Thumbnail sosial tetap dapat dibuat untuk materi nonaktif atau tertutup.

## Integritas halaman

- `window.blur` tidak digunakan karena membuka pengaturan Wi-Fi dapat memicu kehilangan fokus.
- Hanya `visibilitychange` tersembunyi selama minimal 10 detik yang menambah `app_hidden_count`.
- Field historis `tab_switch_count` dan `page_leave_attempt_count` tetap diterima server agar browser lama kompatibel, tetapi tidak dipakai dalam indikator admin baru.

## Dispensasi partisipasi

- Tabel `perpustakaan_literasi_dispensations` menyimpan satu status per materi-siswa: `sick` atau `mt_test`.
- Dispensasi mempunyai snapshot nama/kelas, admin dan waktu konfirmasi, catatan opsional, serta soft delete.
- Respons aktif maupun respons di Sampah mencegah pemberian dispensasi.
- Jika siswa yang memiliki dispensasi kemudian submit, hook respons otomatis membatalkan dispensasi.
- Metrik partisipasi dan ranking jumlah pengisi menghitung `jawaban + dispensasi`.
- Nilai, jawaban benar/salah, akurasi, plagiasi, dan total jawaban selalu hanya memakai respons nyata.
- Tombol **Salin daftar untuk WhatsApp** membuat teks per kelas dari siswa belum mengisi dan siswa dispensasi. Dispensasi tetap menampilkan nama dengan kode `[SAKIT]` atau `[TES MT]`; siswa dengan jawaban di Sampah tidak dicampurkan ke daftar ini.
- Clipboard diproses sepenuhnya di browser tanpa request baru. Jika Clipboard API ditolak browser, tersedia fallback seleksi teks/manual copy.

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
