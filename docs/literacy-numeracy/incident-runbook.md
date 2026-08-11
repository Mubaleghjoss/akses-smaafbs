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
3. Periksa gateway, internet umum, DNS, TCP 443, HTTPS, latensi, dan log CSV v2.
4. Jika hotspot/VPN normal sementara Wi-Fi sekolah gagal, fokus pada ONT/router, DNS, IPv6/MTU, NAT/session table, atau jalur ISP.
5. Jangan menganggap Laravel menolak koneksi jika request tidak muncul di access log server.
6. Halaman fallback Service Worker berarti browser tidak memperoleh respons navigasi; bedakan dengan satu baris HTTP 503 yang benar-benar tercatat di access log.
7. Gunakan kartu Konektivitas dan Pengunjung untuk melihat event browser yang baru terkirim setelah koneksi pulih.
8. Untuk audit hosting per jam jalankan `php artisan app:traffic-audit --date=YYYY-MM-DD --from=06:00 --to=10:00 --school-ip=IP_SAAT_ITU`.

## Topologi jaringan sekolah

1. Router kelas harus mode AP: DHCP/NAT/firewall mati dan uplink LAN-ke-LAN.
2. Hanya router utama atau ONT yang membagikan DHCP, dengan kapasitas minimal 200–250 alamat dan lease 8–12 jam.
3. Jangan mengganti MTU, mematikan IPv6, atau mengubah ONT menjadi bridge hanya berdasarkan satu foto gangguan.
4. Jika tiga sesi monitor menunjukkan gateway normal tetapi internet gagal, kirim log bertimestamp ke ISP.
5. Bridge ONT dan router utama khusus hanya dilakukan bersama ISP setelah NAT/session ONT terbukti menjadi batas; VLAN, TR069, dan VoIP harus dipertahankan.

## Jawaban tidak tersimpan

1. Periksa error validasi sebelum mencari 500.
2. Pastikan jawaban tidak melewati batas karakter.
3. Periksa apakah respons lama berada di Sampah dan masih memegang unique key.
4. Gunakan kode edit jika respons sudah pernah masuk.
5. Periksa event submit dan tiket sebelum menyimpulkan data hilang.
6. Jika materi tidak tampil di daftar tetapi link dapat dibuka, itu perilaku normal: direct link tetap menerima jawaban setelah waktu buka.
7. Jika waktu `opens_at` belum tiba, form sengaja tidak ditampilkan dan submit ditolak.

## Perangkat dipakai bergantian

1. Setelah submit pastikan browser berada di halaman Struk Pengiriman, bukan halaman edit.
2. Struk tidak boleh menampilkan soal atau jawaban.
3. Tekan Isi Murid Berikutnya; tombol ini memakai replace dan membuka form kosong.
4. Jika kode edit perlu disimpan, salin sebelum memuat ulang struk karena data identitas hanya berada di flash session.

## Sakit atau Tes MT

1. Buka Rekap Materi lalu Status Pengisian Materi.
2. Pada siswa Belum Mengisi, pilih Sakit atau Tes MT.
3. Pastikan siswa berpindah ke bagian Dispensasi dan Total Responden bertambah.
4. Jangan membuat jawaban palsu. Dispensasi tidak memiliki nilai, kode edit, atau analisis plagiasi.
5. Jika siswa akhirnya mengerjakan, submit nyata otomatis mencabut dispensasi.

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
