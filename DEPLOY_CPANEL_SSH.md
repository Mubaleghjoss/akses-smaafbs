# Panduan Deploy ke GitHub dan cPanel SSH

Panduan ini dibuat untuk deploy aplikasi Laravel ini ke domain:

```text
https://app.smaafbs.sch.id
```

Repo GitHub:

```text
https://github.com/Mubaleghjoss/akses-smaafbs.git
```

Ikuti urutan dari atas ke bawah. Bagian "Update berikutnya" dipakai setelah setup pertama selesai.

## 1. Alur sederhananya

Ada 2 tempat kerja:

1. Laptop/lokal: tempat edit kode di `E:\xampp\htdocs\akses2-laravel`.
2. Server cPanel: tempat website online berjalan.

Alurnya:

```text
Laptop -> push ke GitHub -> server pull dari GitHub -> website online berubah
```

Jangan upload file `.env` ke GitHub. `.env` berisi password database server dan harus dibuat langsung di cPanel.

## 2. Syarat di cPanel

Pastikan hosting/cPanel menyediakan:

- SSH atau Terminal.
- PHP 8.2 atau lebih baru.
- Composer.
- MySQL/MariaDB.
- Node.js 20.19 atau lebih baru, lebih aman pilih Node.js 22 jika tersedia.
- Domain/subdomain `app.smaafbs.sch.id`.

Extension PHP yang biasanya dibutuhkan Laravel:

```text
bcmath, ctype, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip
```

Jika Composer gagal karena extension kurang, aktifkan dari menu cPanel "Select PHP Version" atau minta bantuan hosting.

## 3. Push perubahan dari laptop ke GitHub

Buka PowerShell di laptop, lalu jalankan:

```powershell
cd E:\xampp\htdocs\akses2-laravel
git status
git add -A
git commit -m "Update aplikasi"
git push origin main
```

Catatan:

- Kalau `git commit` menulis `nothing to commit`, artinya belum ada perubahan baru.
- Kalau `git push` gagal login, login dulu ke GitHub dari Git Credential Manager atau gunakan token GitHub.
- File `.env`, `vendor`, `node_modules`, dan `public/build` memang tidak ikut GitHub.

## 4. Setup pertama di cPanel

Masuk SSH:

```bash
ssh CPANEL_USER@SERVER_HOST
```

Ganti `CPANEL_USER` dan `SERVER_HOST` sesuai akun hosting.

Masuk ke folder home:

```bash
cd ~
```

Clone repo:

```bash
git clone https://github.com/Mubaleghjoss/akses-smaafbs.git akses-smaafbs
cd akses-smaafbs
```

Jika repo GitHub private, clone HTTPS bisa gagal. Solusinya: jadikan repo public sementara, atau pasang SSH key/deploy key GitHub di cPanel.

## 5. Atur document root domain

Di cPanel, buka menu domain/subdomain untuk `app.smaafbs.sch.id`.

Set document root ke folder `public` milik aplikasi:

```text
/home/CPANEL_USER/akses-smaafbs/public
```

Ganti `CPANEL_USER` dengan username cPanel.

Ini penting. Laravel harus dibuka dari folder `public`, bukan dari root project.

Jika cPanel tidak mengizinkan document root ke folder itu, minta hosting mengarahkannya ke:

```text
/home/CPANEL_USER/akses-smaafbs/public
```

## 6. Buat database di cPanel

Di cPanel:

1. Buka "MySQL Databases".
2. Buat database baru, contoh: `cpaneluser_akses`.
3. Buat user database baru.
4. Beri user itu akses penuh ke database.
5. Catat nama database, username database, dan password.

## 7. Buat file .env di server

Masih di folder aplikasi:

```bash
cd ~/akses-smaafbs
cp .env.example .env
nano .env
```

Isi bagian penting seperti ini:

```env
APP_NAME="Akses SMAAFBS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.smaafbs.sch.id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ISI_NAMA_DATABASE_CPANEL
DB_USERNAME=ISI_USERNAME_DATABASE_CPANEL
DB_PASSWORD=ISI_PASSWORD_DATABASE_CPANEL

SESSION_DRIVER=database
CACHE_STORE=database
CACHE_LIMITER_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

Simpan di nano:

```text
CTRL + O, Enter, CTRL + X
```

## 8. Install dependency pertama kali

Masih di server:

```bash
cd ~/akses-smaafbs
composer install --no-dev --optimize-autoloader
```

Jika `composer` tidak dikenali, coba:

```bash
/opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
```

Install dan build asset:

```bash
npm ci
npm run build
```

Jika `npm` tidak dikenali, aktifkan Node.js dari cPanel. Pilih Node.js 22 jika tersedia.

### Catatan penting aset dan file upload

Di project ini file upload admin tidak memakai folder default Laravel `storage/app/public`.
Konfigurasi `disk public` diarahkan ke:

```text
public/storage
```

Artinya:

- Foto, dokumen, PDF, logo, favicon, lampiran, bukti pembayaran, dan upload Filament disimpan di `public/storage`.
- `storage/app/public` bukan folder utama upload untuk project ini.
- Database menyimpan path relatif seperti `news/xxx.jpg`, `site-branding/logo/xxx.png`, atau `berkas_guru/xxx.pdf`, jadi backup harus selalu mencakup database dan folder `public/storage`.
- Jangan upload file `public/hot` ke server production. File itu hanya untuk Vite dev server lokal.

Asset frontend hasil build ada di:

```text
public/build
```

Folder/folder asset statis lain yang tetap perlu ada di server:

```text
public/css
public/js
public/vendor
public/fonts
public/storage
```

## 9. Generate key dan siapkan Laravel

Jalankan:

```bash
php artisan key:generate --force
php artisan migrate --force
mkdir -p public/storage
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jangan jalankan `php artisan key:generate` lagi setelah website sudah dipakai, kecuali memang paham akibatnya. APP_KEY dipakai untuk data terenkripsi/session.

Atur permission jika ada error menulis cache/log:

```bash
mkdir -p public/storage storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 public/storage storage bootstrap/cache
```

## 10. Cek website

Buka:

```text
https://app.smaafbs.sch.id
https://app.smaafbs.sch.id/admin
```

Kalau muncul error 500, cek log:

```bash
cd ~/akses-smaafbs
tail -n 80 storage/logs/laravel.log
```

## 11. Update berikutnya dari laptop

Setelah edit kode di laptop:

```powershell
cd E:\xampp\htdocs\akses2-laravel
git status
git add -A
git commit -m "Update aplikasi"
git push origin main
```

## 12. Update berikutnya di server

Masuk SSH:

```bash
ssh CPANEL_USER@SERVER_HOST
```

Lalu jalankan:

```bash
cd ~/akses-smaafbs
bash scripts/cpanel-update.sh
```

Script itu akan menjalankan:

- pull kode terbaru dari GitHub,
- install/update Composer dependency,
- install/build asset frontend,
- migrate database,
- refresh cache Laravel.

## 13. Kalau update gagal

Jika `git pull` gagal karena ada perubahan lokal di server:

```bash
git status
```

Biasanya penyebabnya ada file yang diedit langsung di server. Jangan langsung menjalankan `git reset --hard` kalau belum yakin, karena itu bisa menghapus perubahan lokal.

Jika tampilan CSS/JS berantakan:

```bash
npm ci
npm run build
php artisan view:cache
```

Jika config `.env` sudah diubah tapi belum terbaca:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Jika storage gambar/file tidak muncul:

```bash
ls -lah public/storage
chmod -R 775 public/storage
php artisan optimize:clear
```

Catatan: untuk project ini jangan mengandalkan `php artisan storage:link`, karena `config/filesystems.php` mengarahkan disk public langsung ke `public/storage`.

Jika database belum ada tabel:

```bash
php artisan migrate --force
```

## 14. Backup sebelum update besar

Sebelum update besar, backup dulu:

1. Backup database dari cPanel phpMyAdmin atau fitur Backup.
2. Backup folder `public/storage` karena folder ini berisi file upload penting.
3. Setelah backup, baru jalankan update server.
