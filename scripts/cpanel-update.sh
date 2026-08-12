#!/usr/bin/env bash
set -euo pipefail

BRANCH="${1:-main}"
PUBLIC_WEB_ROOT="${PUBLIC_WEB_ROOT:-${HOME}/public_html/web/app}"

echo "==> Pull kode terbaru dari GitHub (${BRANCH})"
git pull --ff-only origin "${BRANCH}"

echo "==> Install/update dependency PHP"
if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="$(command -v composer)"
elif [ -x "${HOME}/bin/composer" ]; then
    COMPOSER_BIN="${HOME}/bin/composer"
elif [ -x /opt/cpanel/composer/bin/composer ]; then
    COMPOSER_BIN="/opt/cpanel/composer/bin/composer"
else
    echo "ERROR: composer tidak ditemukan. Aktifkan Composer di cPanel atau hubungi hosting."
    exit 1
fi

if php -r 'exit(function_exists("proc_open") ? 0 : 1);'; then
    "${COMPOSER_BIN}" install --no-dev --optimize-autoloader
else
    echo "INFO: proc_open nonaktif; jalankan Composer tanpa script lalu package discovery via Artisan."
    "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-scripts
    php artisan package:discover --ansi
fi

echo "==> Siapkan asset frontend"
BUILD_ASSETS_ON_SERVER="${BUILD_ASSETS_ON_SERVER:-false}"
if [ "${BUILD_ASSETS_ON_SERVER}" = "true" ]; then
    if ! command -v npm >/dev/null 2>&1; then
        echo "ERROR: BUILD_ASSETS_ON_SERVER=true tetapi npm tidak tersedia."
        exit 1
    fi

    NPM_DEPLOY_CACHE="${HOME}/tmp/akses-app-npm-cache"
    mkdir -p "${NPM_DEPLOY_CACHE}"
    npm ci --cache "${NPM_DEPLOY_CACHE}"
    npm run build
    npm cache clean --force --cache "${NPM_DEPLOY_CACHE}" || true
elif [ -f public/build/manifest.json ]; then
    echo "INFO: gunakan public/build yang sudah dikompilasi dan dikomit dari laptop."
else
    echo "ERROR: public/build/manifest.json tidak tersedia. Build aset di laptop atau set BUILD_ASSETS_ON_SERVER=true."
    exit 1
fi
rm -f public/hot

if [ -d "${PUBLIC_WEB_ROOT}" ] && [ "${PUBLIC_WEB_ROOT}" != "$(pwd)/public" ]; then
    echo "==> Sinkron asset frontend ke document root (${PUBLIC_WEB_ROOT})"
    mkdir -p "${PUBLIC_WEB_ROOT}/build"
    cp -a public/build/. "${PUBLIC_WEB_ROOT}/build/"

    # CSS ini dimuat langsung oleh AdminPanelProvider dan tidak masuk bundel Vite.
    # Document root produksi terpisah dari public/ repo, jadi file harus ikut disalin.
    mkdir -p "${PUBLIC_WEB_ROOT}/css"
    cp public/css/filament-admin-responsive.css "${PUBLIC_WEB_ROOT}/css/filament-admin-responsive.css"
    cp public/css/filament-admin-auth.css "${PUBLIC_WEB_ROOT}/css/filament-admin-auth.css"

    # JavaScript fallback admin juga dimuat langsung oleh AdminPanelProvider.
    mkdir -p "${PUBLIC_WEB_ROOT}/js"
    cp public/js/filament-admin-fallback.js "${PUBLIC_WEB_ROOT}/js/filament-admin-fallback.js"
    cp public/js/pwa-registration.js "${PUBLIC_WEB_ROOT}/js/pwa-registration.js"
    cp public/js/filament-admin-passkeys.js "${PUBLIC_WEB_ROOT}/js/filament-admin-passkeys.js"

    # Header cache aset publik harus ikut aktif pada document root produksi.
    cp public/.htaccess "${PUBLIC_WEB_ROOT}/.htaccess"

    # Lindungi dokumen privat-yang-masih-berada-di-disk-publik dari URL langsung.
    # Preview/download tetap dilayani controller yang terotorisasi.
    mkdir -p "${PUBLIC_WEB_ROOT}/storage"
    install -m 0644 public/storage/.htaccess "${PUBLIC_WEB_ROOT}/storage/.htaccess"
    if [ ! -f "${PUBLIC_WEB_ROOT}/storage/.htaccess" ]; then
        echo "ERROR: guard dokumen guru gagal dipasang pada document root."
        exit 1
    fi

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
