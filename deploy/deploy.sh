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
