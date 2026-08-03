#!/usr/bin/env bash
set -euo pipefail

MODE="${1:---dry-run}"
HOME_ROOT="${HOME}"
NPM_HOME_CACHE="${HOME_ROOT}/.npm"
NPM_TMP_CACHE="${HOME_ROOT}/tmp/npm-cache"

case "${MODE}" in
    --dry-run|--clean-npm-cache) ;;
    *) echo "Gunakan --dry-run atau --clean-npm-cache"; exit 2 ;;
esac

echo "==> Ringkasan penggunaan home hosting"
du -h --max-depth=1 "${HOME_ROOT}" 2>/dev/null | sort -hr | head -30 || true
HOSTING_USED_BYTES="$(du -sb "${HOME_ROOT}" 2>/dev/null | awk '{print $1}')"
if [ -n "${HOSTING_USED_BYTES}" ] && [ -f "${HOME_ROOT}/akses-app/artisan" ]; then
    (cd "${HOME_ROOT}/akses-app" && php artisan app:storage-audit --used-bytes="${HOSTING_USED_BYTES}")
fi

echo "==> Cache npm yang diizinkan"
du -sh "${NPM_HOME_CACHE}" "${NPM_TMP_CACHE}" 2>/dev/null || true

echo "==> Kandidat salinan aplikasi lama (tidak pernah dihapus script ini)"
find "${HOME_ROOT}" -maxdepth 1 -type d \( -name 'tagihan-app-previous-*' -o -name 'tagihan-public-previous-*' \) -print 2>/dev/null | sort || true

if [ "${MODE}" = "--dry-run" ]; then
    echo "DRY-RUN: tidak ada file dihapus."
    exit 0
fi

for target in "${NPM_HOME_CACHE}" "${NPM_TMP_CACHE}"; do
    resolved_parent="$(cd "$(dirname "${target}")" && pwd -P)"
    resolved_target="${resolved_parent}/$(basename "${target}")"
    case "${resolved_target}" in
        "${HOME_ROOT}/.npm"|"${HOME_ROOT}/tmp/npm-cache") ;;
        *) echo "TARGET DITOLAK: ${resolved_target}"; exit 3 ;;
    esac

    if [ -d "${resolved_target}" ] && command -v npm >/dev/null 2>&1; then
        npm cache clean --force --cache "${resolved_target}" || true
    fi
done

echo "Cache npm sudah dibersihkan melalui target allowlist."
