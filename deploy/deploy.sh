#!/usr/bin/env bash
# Yangilanishlarni serverga chiqarish.
# Ishlatish: sudo -u www-data APP_DIR=/var/www/mt.allpro.uz ./deploy/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mt.allpro.uz}"
BRANCH="${BRANCH:-main}"

echo "==> Kod yangilanmoqda ($BRANCH)"
cd "$APP_DIR"
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Frontend yig'ilmoqda"
cd "$APP_DIR/frontend"
# Frontend yo'li backend .env'dagi APP_URL'dan olinadi: https://mt.regofis.uz -> /,
# yo'lda ishlasa (ixtiyoriy) https://misol.uz/zoom -> /zoom/. Shunda ikkalasi bir-biridan farq qilib qolmaydi.
if [[ -z "${VITE_BASE_PATH:-}" && -f "$APP_DIR/backend/.env" ]]; then
    app_url="$(grep -E '^APP_URL=' "$APP_DIR/backend/.env" | tail -n 1 | cut -d= -f2- | tr -d "\"' \r")"
    rest="${app_url#*://}"
    if [[ "$rest" == */* ]]; then base="/${rest#*/}"; else base="/"; fi
    export VITE_BASE_PATH="${base%/}/"
fi
echo "    VITE_BASE_PATH=${VITE_BASE_PATH:-/}"
npm ci --no-audit --no-fund
npm run build

echo "==> Backend yangilanmoqda"
cd "$APP_DIR/backend"
php artisan down --retry=15 || true
trap 'php artisan up' EXIT

composer install --no-dev --optimize-autoloader --no-interaction --no-progress
php artisan migrate --force
php artisan optimize
php artisan queue:restart

echo "==> Tayyor"
