#!/usr/bin/env bash
set -euo pipefail

BRANCH="${1:-main}"
PUBLIC_WEB_ROOT="${PUBLIC_WEB_ROOT:-${HOME}/public_html/web/app}"

echo "==> Pull kode terbaru dari GitHub (${BRANCH})"
git pull --ff-only origin "${BRANCH}"

echo "==> Install/update dependency PHP"
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader
elif [ -x "${HOME}/bin/composer" ]; then
    "${HOME}/bin/composer" install --no-dev --optimize-autoloader
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
elif [ -f public/build/manifest.json ]; then
    echo "INFO: npm tidak tersedia; gunakan aset public/build yang sudah dikomit."
    rm -f public/hot
else
    echo "ERROR: npm tidak ditemukan dan aset public/build belum tersedia."
    exit 1
fi

if [ -d "${PUBLIC_WEB_ROOT}" ] && [ "${PUBLIC_WEB_ROOT}" != "$(pwd)/public" ]; then
    echo "==> Sinkron asset frontend ke document root (${PUBLIC_WEB_ROOT})"
    mkdir -p "${PUBLIC_WEB_ROOT}/build"
    cp -a public/build/. "${PUBLIC_WEB_ROOT}/build/"

    # CSS ini dimuat langsung oleh AdminPanelProvider dan tidak masuk bundel Vite.
    # Document root produksi terpisah dari public/ repo, jadi file harus ikut disalin.
    mkdir -p "${PUBLIC_WEB_ROOT}/css"
    cp public/css/filament-admin-responsive.css "${PUBLIC_WEB_ROOT}/css/filament-admin-responsive.css"
    cp public/css/filament-admin-auth.css "${PUBLIC_WEB_ROOT}/css/filament-admin-auth.css"

    # Header cache aset publik harus ikut aktif pada document root produksi.
    cp public/.htaccess "${PUBLIC_WEB_ROOT}/.htaccess"

    rm -f "${PUBLIC_WEB_ROOT}/hot"
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
