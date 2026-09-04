# SMA AFBS — Hermes + Pi Operating Rules

## Project identity
Project utama:
`/home/hermesadmin/projects/akses-smaafbs`

Runtime staging:
`/var/www/app-smaafbs-staging`

Production runtime:
`/var/www/app-smaafbs-production`

Staging URL:
`https://staging-app.smaafbs.sch.id`

## Coding workflow
Untuk pekerjaan coding, debugging, refactor, UI, Laravel, test, atau analisis source:
- Hermes bertindak sebagai orchestrator.
- Gunakan Pi untuk pekerjaan coding.
- Default pekerjaan coding normal: Pi level MENENGAH.
- Gunakan level RINGAN untuk perubahan kecil.
- Gunakan MENENGAH-BERAT untuk perubahan lintas banyak file.
- Gunakan BERAT hanya untuk pekerjaan arsitektural atau kompleks.
- Analisis sebelum mengubah file.
- Kerjakan hanya di project workspace.
- Jangan edit runtime staging atau production secara langsung.

## Source safety
Sebelum mengubah kode:
1. cek `git status`
2. cek branch
3. jangan menimpa perubahan lama yang belum jelas asalnya
4. jika ada perubahan tak terduga, laporkan dulu

Jangan otomatis:
- force push
- reset --hard
- git clean
- menghapus branch
- menghapus backup

## Testing
Setelah perubahan:
- jalankan test yang paling relevan terlebih dahulu
- jangan menganggap legacy test failure sebagai regresi baru
- baseline-aware gate:
  `~/bin/test-app-smaafbs-baseline`

Sebelum deploy staging wajib melewati:
`~/bin/deploy-app-staging check`

Jika muncul kegagalan baru di luar baseline:
STOP dan laporkan.

## Database safety
Jangan pernah otomatis menjalankan:
- migrate:fresh
- migrate:reset
- db:wipe
- DROP DATABASE
- DROP TABLE
- perubahan APP_KEY
- mengganti database production

Jangan menjalankan migration ke production tanpa persetujuan eksplisit.

Staging database:
`smaafbs_staging`

## Deployment rules
Permintaan biasa seperti:
"perbaiki", "buat", "ubah", "refactor", "cek", "analisa"

BERARTI:
coding + test saja.
JANGAN deploy.

Hanya deploy jika user secara eksplisit mengatakan:
"deploy staging"

Untuk deploy staging gunakan:
`~/bin/deploy-app-staging deploy`

Sebelum deploy:
- git working tree harus bersih
- safety check harus lulus
- baseline test gate tidak boleh memiliki kegagalan baru
- migration tidak boleh Pending

Jika deploy gagal:
- jangan memaksa
- biarkan rollback berjalan
- laporkan penyebabnya

Production tidak boleh dideploy hanya karena user mengatakan "deploy".
Untuk production harus ada perintah eksplisit:
"deploy production"

Dan sebelum production:
- tampilkan ringkasan perubahan
- tampilkan hasil test
- tampilkan risiko
- minta persetujuan final

## Staging health
Smoke test yang benar:
- HOME = HTTP 200
- ADMIN = HTTP 302

Host:
`staging-app.smaafbs.sch.id`

## Git behavior
Pi boleh mengubah source dan menjalankan test.

Jangan push GitHub otomatis kecuali diminta.

Setelah pekerjaan selesai selalu laporkan:
- ringkasan perubahan
- file yang diubah
- test yang dijalankan
- hasil test
- git status
- apakah siap untuk staging

## Telegram shorthand
Jika user mengatakan:

"Pi, perbaiki X"
→ gunakan Pi, edit source, test terkait, jangan deploy.

"cek X"
→ analisis dulu, jangan mengubah file kecuali memang diminta.

"deploy staging"
→ lakukan safety check dan deploy staging saja.

"status"
→ laporkan branch, commit, git status, staging health, dan proses relevan.

## Absolute prohibitions
Tanpa persetujuan eksplisit jangan:
- menyentuh Rumahweb production
- mengganti DNS
- restart service production
- mengubah firewall
- mengubah SSH
- menghapus database
- menghapus storage
- mengubah APP_KEY
- mengirim secret ke Telegram/log
