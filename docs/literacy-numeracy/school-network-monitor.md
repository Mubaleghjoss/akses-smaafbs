# Monitor Jaringan Sekolah

Monitor lokal membedakan gangguan gateway/LAN, internet/ONT, DNS, TCP 443, dan respons HTTPS aplikasi. Monitor tidak mempercepat jaringan dan tidak berada di jalur submit murid.

## Berkas dan jadwal

- Pemeriksa: `scripts/monitor-school-app.ps1`
- Launcher tanpa konsol: `scripts/monitor-school-app-hidden.vbs`
- Installer: `scripts/install-literacy-school-monitor.ps1`
- Kontrol: `scripts/literacy-monitor-control.ps1`
- Jadwal: setiap 1 menit
- Log lokal: `storage/logs/literacy-school-monitor-v2.csv`
- State lokal: `storage/app/private/literacy-school-monitor-state.json`

## Instalasi laptop sekolah

Jalankan dari PowerShell pada akun Windows yang akan memakai shortcut:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\install-literacy-school-monitor.ps1
```

Installer mengganti task `SMA AFBS Literacy Network Monitor`, menjalankannya melalui `wscript.exe`, dan membuat:

- Aktifkan Monitor Jaringan
- Nonaktifkan Monitor Jaringan
- Cek Status Monitor Jaringan

Kontrol sebenarnya berada di laptop. Panel admin hanya membaca heartbeat terakhir dan tidak mencoba mengendalikan laptop ketika koneksi sekolah sedang putus.

## Status admin

- Aktif: heartbeat masih segar.
- Terlambat: tidak ada heartbeat selama tiga menit atau sesuai batas `literacy.school_monitor.stale_minutes`.
- Nonaktif: shortcut menonaktifkan task dan berhasil mengirim perubahan status.
- Belum terpasang: belum ada data monitor.

Jika nonaktif dilakukan saat situs tidak terjangkau, status lokal tetap benar tetapi admin baru memperoleh keadaan terbaru setelah koneksi tersedia.

## Keamanan

Token berada di `storage/app/private/literacy-school-monitor-token.txt`, bukan Git. Endpoint memakai bearer token dan limiter khusus. Jangan menyalin token ke log atau dokumentasi.

IP asal heartbeat hanya disimpan sebagai hash HMAC. Hash ini dipakai untuk menandai telemetri browser yang pulih melalui jaringan sekolah meskipun IP publik IndiBiz berubah.

## Interpretasi hasil

- `GATEWAY_OR_LAN_FAILED`: periksa Wi-Fi, AP, kabel, uplink, dan gateway lokal.
- `INTERNET_OR_ONT_FAILED`: gateway masih terlihat tetapi koneksi keluar gagal; periksa ONT atau ISP.
- `DNS_FAILED`: koneksi internet tersedia tetapi nama domain gagal diterjemahkan.
- `TCP_443_FAILED`: DNS berhasil tetapi koneksi HTTPS ke host tidak terbentuk.
- `HTTP_503`/`HTTP_504`: server menjawab sedang tidak tersedia.
- `HTTPS_RESET_OR_TIMEOUT`: TCP terbentuk tetapi probe `/up` tidak memperoleh respons lengkap.

Log aktif dirotasi setelah 5 MB dan arsip lokal yang lebih lama dari 30 hari dibersihkan otomatis.

## Telemetri browser anonim

Service Worker menyimpan maksimal 20 event konektivitas di browser ketika navigasi gagal. Setelah koneksi pulih, event dikirim tanpa nama, NISN, jawaban, kode edit, atau URL lengkap. Server hanya menerima kelompok halaman, waktu, jenis gangguan, versi worker, ID browser yang langsung di-hash, dan hash IP pemulihan. Data mentah disimpan 30 hari.
