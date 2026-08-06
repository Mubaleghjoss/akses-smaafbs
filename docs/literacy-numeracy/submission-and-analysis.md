# Submit, Antrean, dan Analisis

## Alur

1. Browser menyimpan draf di `sessionStorage`.
2. Browser meminta tiket antrean.
3. Tiket `admitted` memberi izin submit akhir.
4. Controller memvalidasi ulang seluruh payload terhadap pertanyaan di database.
5. Respons, jawaban, dan perubahan tiket menjadi `completed` disimpan dalam satu transaksi.
6. Slot gagal selalu dilepas melalui `finally`; tiket yang sudah `completed` tidak dapat dibatalkan kembali.
7. Job kemiripan dijadwalkan; halaman sukses tidak menunggu analisis.
8. Browser berpindah dengan `location.replace()` ke struk session tanpa soal dan jawaban.

Textarea, radio Benar/Salah, dan dropdown Menjodohkan ikut draf. FormData tetap kecil karena tidak ada file audio.

## Status submit

- `OK-LANGSUNG`: tidak menunggu antrean.
- `Q-ANTRE`: sempat menunggu tiket.
- `R-429`, `R-503`, `R-RETRY`: browser melakukan retry sebelum berhasil.
- `LEGACY`: respons dibuat sebelum pencatatan status.

Retry memakai request ID dan tiket yang sama agar koneksi putus tidak membuat respons ganda. JavaScript memakai protokol `async-v2`; seluruh hasil final berupa JSON dan redirect HTTP tidak pernah diikuti sebagai bukti sukses.

### Kontrak async-v2

- Browser mengirim `X-Literacy-Client: async-v2` dan server membalas `X-Literacy-Protocol: 2` serta trace ID.
- `200 completed` hanya dikirim setelah transaksi respons dan tiket berhasil commit.
- `422 validation_failed` atau `verification_mismatch` menghentikan retry dan membuka field yang perlu diperbaiki.
- `409 already_submitted` dan `response_in_trash` menampilkan tindakan yang tepat tanpa mengirim ulang.
- `425 waiting/processing` berarti tiket belum dapat menjalankan submit final.
- Satu klik Kirim hanya boleh membuat satu POST final dan maksimal satu replay idempoten setelah status tiket diperiksa.
- Endpoint `POST submission-queue/{ticket}/receipt` memulihkan flash session Struk dari `result_response_id`; endpoint status tidak pernah membocorkan kode edit.
- Tiket baru mempunyai `request_key_hash` unik. Tiket gagal tanpa hasil didaur ulang untuk request ID yang sama.

### Pesan status untuk murid

- Pesan retry dibedakan berdasarkan penyebab: koneksi perangkat, HTTP 429, timeout 408/504, layanan 502/503, atau gangguan aplikasi 500.
- Hanya status tiket `waiting` yang memakai kalimat **sudah masuk antrean** dan menampilkan posisi. Gangguan koneksi tidak boleh disebut antrean penuh.
- Selama retry, isian tetap berada di form dan disalin ke `sessionStorage`; murid tidak perlu menekan Kirim berulang dan harus mempertahankan tab tetap terbuka.
- Draf bukan bukti respons sudah tersimpan di server. Bukti konfirmasi yang mudah dipahami murid adalah halaman **Struk Pengiriman** beserta kode edit.
- Jika status tetap tidak dapat dipastikan, browser berhenti melakukan POST dan hanya memeriksa tiket. Murid diminta tidak menekan Kirim berulang. Request ID yang sama menjaga satu replay pemulihan tetap idempoten.
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
- Kalimat **Jawaban tersimpan** hanya boleh tampil setelah tiket `completed`. Draf lokal dan status pemeriksaan bukan bukti penyimpanan server.

## Konkurensi dan limiter

- Batas produksi tetap 10 submit aktif agar request halaman dan admin memiliki ruang.
- Promosi FIFO memakai cache lock database nonblocking. Request yang tidak memperoleh lock tidak menunggu proses PHP.
- Status tiket normal hanya membaca tiket; pembersihan/promosi dijalankan ketika tiket menunggu atau telah kedaluwarsa.
- Transaksi penting mencoba ulang deadlock maksimal tiga kali dan tidak memakai baris singleton sebagai global `lockForUpdate`.
- Limiter final: 4 per tiket/request ID, 30 per sesi, dan 1.200 per IP per menit.
- Limiter tiket: 30 per sesi dan 1.200 per IP. Status/pemulihan: 120 per sesi dan 3.000 per IP.
- Semua throttle Literasi mengembalikan JSON 429 dengan `Retry-After`; satu IP publik sekolah tidak boleh menjadi limiter utama siswa.
- Respons 429 dengan header `X-Literacy-Throttle` dihitung sebagai limiter aplikasi. Browser mencatat 429 tanpa header tersebut sebagai indikasi hosting/jaringan agar dua sumber gangguan tampil terpisah di panel admin.

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

- Tabel `perpustakaan_literasi_dispensations` menyimpan satu status per materi-siswa: `permission`, `sick`, atau `mt_test`.
- Status `permission` wajib memiliki catatan 5-1.000 karakter. Catatan hanya tampil kepada admin dan salinan WhatsApp petugas; struk murid hanya menampilkan label Izin.
- Dispensasi mempunyai snapshot nama/kelas, admin dan waktu konfirmasi, catatan opsional, serta soft delete.
- Respons aktif maupun respons di Sampah mencegah pemberian dispensasi.
- Jika siswa yang memiliki dispensasi kemudian submit, hook respons otomatis membatalkan dispensasi.
- Metrik partisipasi dan ranking jumlah pengisi menghitung `jawaban + dispensasi`.
- Nilai, jawaban benar/salah, akurasi, plagiasi, dan total jawaban selalu hanya memakai respons nyata.
- Tombol **Salin daftar untuk WhatsApp** membuat teks per kelas dari siswa belum mengisi dan siswa dispensasi. Dispensasi tetap menampilkan nama dengan kode `[IZIN: keterangan]`, `[SAKIT]`, atau `[TES MT]`; siswa dengan jawaban di Sampah tidak dicampurkan ke daftar ini.
- Clipboard diproses sepenuhnya di browser tanpa request baru. Jika Clipboard API ditolak browser, tersedia fallback seleksi teks/manual copy.

## Analisis

- Queue: `literacy-analysis`.
- Hanya jawaban Esai dengan deteksi aktif yang dibandingkan.
- Benar/Salah dan Menjodohkan dilewati seluruhnya.
- Jawaban hanya dibandingkan dengan respons aktif yang dikirim lebih dahulu pada materi dan pertanyaan yang sama.
- Ambang bawaan adalah 80% dan dapat diatur melalui `LITERACY_SIMILARITY_THRESHOLD`.
- Setiap jawaban menyimpan maksimal satu pembanding terdahulu dengan skor tertinggi; jika seri, respons paling awal dipilih.
- Jawaban yang sama dengan Kunci Jawaban resmi, termasuk calon pembandingnya, tidak dibuatkan indikasi kemiripan karena kesamaan tersebut memang diharapkan.
- Hasil otomatis adalah indikasi untuk ditinjau guru, bukan vonis plagiasi.
- Aksi admin analisa ulang hanya menjadwalkan job.
- Versi analisis mencegah hasil edit lama menimpa jawaban terbaru.
- Edit jawaban menjadwalkan ulang respons sesudahnya agar pembanding terkuat tidak memakai isi lama.

Rekonsiliasi data lama:

```bash
php artisan literacy:similarity-reconcile --material=43 --dry-run
php artisan literacy:similarity-reconcile --material=43 --apply --batch=25
```

Perintah dry-run tidak menulis data. Apply hanya menyusun ulang baris indikasi dan menyesuaikan batas karakter pertanyaan berkunci; respons, isi jawaban, nilai, dan kode edit tidak diubah.

## Salinan rekap bulanan WhatsApp

- Ringkasan Literasi menyediakan empat lingkup: keseluruhan, SIGAP 29 Karakter, Literasi, dan Numerasi.
- Analitik lengkap baru dihitung ketika admin menekan tombol lingkup; membuka halaman tidak menghitung keempat rekap sekaligus.
- Lingkup keseluruhan turut memasukkan materi lama yang belum mempunyai `program_category`.
- Periode selalu bulan berjalan pada zona waktu aplikasi. Partisipasi adalah respons nyata ditambah dispensasi, sedangkan siswa unik tidak dihitung rangkap.
- Status **sudah dinilai lengkap** berlaku per respons hanya jika seluruh jawabannya telah dinilai. Respons dengan satu jawaban yang masih kosong tetap masuk **belum dinilai/masih sebagian**.
- Status kemiripan dihitung per siswa unik. `confirmed` lebih kuat daripada `suspected`; status `cleared` tidak disebut plagiasi dan tidak masuk ranking indikasi aktif.
- Semua daftar pada salinan tidak dipotong pagination atau batas tampilan admin. Hanya ranking kelas jawaban benar yang sengaja dibatasi tiga kelas.
- Teks disimpan sementara hanya pada state Livewire milik sesi admin, ditampilkan dalam modal pratinjau, lalu disalin di browser. Nama siswa tidak dicatat ke log, cache bersama, atau endpoint publik.

## Aturan perubahan

- Jangan menambah `sleep()` pada request publik.
- Jangan mengunggah rekaman suara.
- Jangan menjalankan `similar_text()` di controller submit.
- Jangan menghapus unique key respons siswa tanpa mengganti perlindungan duplikasinya.
- Validasi browser hanya bantuan; validasi server tetap wajib.
