# Runbook Insiden Literasi Numerasi

## Murid melihat 429 atau 503

1. Buka Ringkasan Literasi dan periksa antrean, slot aktif, retry, worker, dan cron.
2. Periksa status submit responden: `Q-ANTRE`, `R-429`, atau `R-503`.
3. Pastikan cache, limiter, session, dan queue produksi memakai database.
4. Pastikan scheduler berjalan tiap menit dan worker bertahap tidak gagal.
5. Jangan menaikkan konkurensi tanpa melihat Entry Process dan CPU hosting.

## ERR_CONNECTION_RESET hanya dari Wi-Fi sekolah

1. Bandingkan hotspot, Wi-Fi gedung lain, dan VPN.
2. Buka shortcut Cek Status Monitor Jaringan.
3. Periksa DNS, TCP 443, HTTPS, latensi, dan log CSV.
4. Jika hotspot/VPN normal sementara Wi-Fi sekolah gagal, fokus pada ONT/router, DNS, IPv6/MTU, NAT/session table, atau jalur ISP.
5. Jangan menganggap Laravel menolak koneksi jika request tidak muncul di access log server.

## Jawaban tidak tersimpan

1. Periksa error validasi sebelum mencari 500.
2. Pastikan jawaban tidak melewati batas karakter.
3. Periksa apakah respons lama berada di Sampah dan masih memegang unique key.
4. Gunakan kode edit jika respons sudah pernah masuk.
5. Periksa event submit dan tiket sebelum menyimpulkan data hilang.

## Analisis lambat

1. Pastikan submit sudah sukses; analisis memang berjalan terpisah.
2. Periksa job `literacy-analysis`, failed jobs, heartbeat scheduler, dan status worker.
3. Objektif tidak seharusnya menghasilkan similarity match.
4. Jadwalkan analisa ulang, jangan menghitung langsung melalui request admin.

## Format catatan insiden

Tambahkan ke `CHANGELOG.md`:

- tanggal dan jam,
- materi/URL,
- gejala dan jaringan,
- bukti status HTTP/log,
- akar masalah terverifikasi atau hipotesis,
- perubahan, commit, deployment,
- hasil verifikasi dan rollback.
