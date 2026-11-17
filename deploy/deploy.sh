#!/usr/bin/env bash
# Yangilanishlarni serverga chiqarish. Kod papkasida ishga tushiring:
#
#   sudo ./deploy/deploy.sh
#
# git, npm va composer — kod papkasi EGASI nomidan ishlaydi (u papkaga yoza oladi, git kalitlari ham
# uniki). php artisan — www-data nomidan (storage va bootstrap/cache fayllari veb-serverniki bo'lib
# qolsin). Shuning uchun "dubious ownership" yoki "Permission denied" xatolari chiqmaydi.
#
# Sozlash (ixtiyoriy): APP_DIR (standart: shu skript turgan repo), BRANCH (main), WEB_USER (www-data).
set -euo pipefail

# Butun skript funksiya ichida: bash uni oldindan to'liq o'qiydi, shuning uchun git pull
# shu faylning o'zini yangilasa ham ishlab turgan nusxa buzilmaydi.
main() {
    # Global (local emas): skript tugaganda ishlaydigan "artisan up" ham ularni ko'rishi kerak.
    app_dir="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
    branch="${BRANCH:-main}"
    web_user="${WEB_USER:-www-data}"
    owner="$(stat -c %U "$app_dir")"

    # Buyruqni berilgan foydalanuvchi nomidan bajaradi (o'zi bo'lmasa — sudo bilan).
    run_as() {
        local user="$1"
        shift
        if [[ "$(id -un)" == "$user" ]]; then
            "$@"
        else
            sudo -H -u "$user" "$@"
        fi
    }
    as_owner() { run_as "$owner" "$@"; }
    artisan() { run_as "$web_user" php "$app_dir/backend/artisan" "$@"; }

    echo "==> Kod papkasi: $app_dir (egasi: $owner), artisan: $web_user"

    echo "==> Kod yangilanmoqda ($branch)"
    as_owner git -C "$app_dir" fetch --prune origin
    as_owner git -C "$app_dir" checkout "$branch"
    as_owner git -C "$app_dir" pull --ff-only origin "$branch"

    echo "==> Frontend yig'ilmoqda"
    cd "$app_dir/frontend"
    # Frontend yo'li backend .env'dagi APP_URL'dan olinadi: https://mt.regofis.uz -> /,
    # yo'lda ishlasa (ixtiyoriy) https://misol.uz/zoom -> /zoom/. Shunda ikkalasi bir-biridan farq qilmaydi.
    local base="${VITE_BASE_PATH:-}"
    if [[ -z "$base" && -f "$app_dir/backend/.env" ]]; then
        local app_url rest
        app_url="$(grep -E '^APP_URL=' "$app_dir/backend/.env" | tail -n 1 | cut -d= -f2- | tr -d "\"' \r")"
        rest="${app_url#*://}"
        if [[ "$rest" == */* ]]; then base="/${rest#*/}"; else base="/"; fi
        base="${base%/}/"
    fi
    echo "    VITE_BASE_PATH=${base:-/}"
    as_owner npm ci --no-audit --no-fund
    as_owner env VITE_BASE_PATH="${base:-/}" npm run build

    echo "==> Backend yangilanmoqda"
    cd "$app_dir/backend"
    artisan down --retry=15 || true
    # Nima bo'lsa ham sayt yopiq qolmasin: artisan ishlamasa, texnik ishlar belgisi qo'lda o'chiriladi.
    trap 'artisan up || run_as "$web_user" rm -f "$app_dir/backend/storage/framework/down"' EXIT

    # --no-scripts: composer www-data'ning bootstrap/cache papkasiga yozmaydi — buni artisan qiladi.
    as_owner env COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
    # Eski paketlar ro'yxati (masalan, o'chirilgan dev-paketlar) qolsa artisan umuman ishga tushmaydi —
    # shuning uchun keshlar artisan'siz o'chiriladi va qayta yaratiladi.
    run_as "$web_user" find "$app_dir/backend/bootstrap/cache" -maxdepth 1 -name '*.php' -delete
    artisan package:discover --ansi
    artisan migrate --force
    artisan optimize
    artisan queue:restart

    echo "==> Tayyor"
}

main "$@"
