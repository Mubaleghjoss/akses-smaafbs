# Monitor Jaringan Sekolah

Monitor lokal membedakan kegagalan DNS, TCP 443, dan respons HTTPS aplikasi. Monitor tidak mempercepat jaringan dan tidak berada di jalur submit murid.

## Berkas dan jadwal

- Pemeriksa: `scripts/monitor-school-app.ps1`
- Launcher tanpa konsol: `scripts/monitor-school-app-hidden.vbs`
- Installer: `scripts/install-literacy-school-monitor.ps1`
- Kontrol: `scripts/literacy-monitor-control.ps1`
- Jadwal: setiap 5 menit
- Log lokal: `storage/logs/literacy-school-monitor.csv`
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
- Terlambat: tidak ada heartbeat sesuai batas `literacy.school_monitor.stale_minutes`.
- Nonaktif: shortcut menonaktifkan task dan berhasil mengirim perubahan status.
- Belum terpasang: belum ada data monitor.

Jika nonaktif dilakukan saat situs tidak terjangkau, status lokal tetap benar tetapi admin baru memperoleh keadaan terbaru setelah koneksi tersedia.

## Keamanan

Token berada di `storage/app/private/literacy-school-monitor-token.txt`, bukan Git. Endpoint memakai bearer token dan limiter khusus. Jangan menyalin token ke log atau dokumentasi.
