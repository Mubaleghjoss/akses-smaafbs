#!/usr/bin/env bash
set -euo pipefail

BRANCH="${1:-main}"

echo "==> Pull kode terbaru dari GitHub (${BRANCH})"
git pull --ff-only origin "${BRANCH}"

echo "==> Install/update dependency PHP"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader
elif [ -x /opt/cpanel/composer/bin/composer ]; then
    /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
else
    echo "ERROR: composer tidak ditemukan. Aktifkan Composer di cPanel atau hubungi hosting."
    exit 1
fi

echo "==> Install/build asset frontend"
if command -v npm >/dev/null 2>&1; then
    npm ci
    npm run build
    rm -f public/hot
else
    echo "ERROR: npm tidak ditemukan. Aktifkan Node.js 20.19+ atau Node.js 22 di cPanel."
    exit 1
fi

echo "==> Siapkan storage dan cache folder"
mkdir -p public/storage storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 public/storage storage bootstrap/cache

echo "==> Jalankan migrasi database"
php artisan migrate --force

echo "==> Refresh cache Laravel"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Selesai. Cek https://app.smaafbs.sch.id"
