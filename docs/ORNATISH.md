# Serverga o'rnatish qo'llanmasi

Bu qo'llanma tizimni **Ubuntu 24.04** serverga noldan o'rnatishni bosqichma-bosqich ko'rsatadi. Misollarda domen `mt.allpro.uz`, papka `/var/www/mt.allpro.uz`. Boshqa domen yoki papka bo'lsa, ularni hamma joyda almashtiring.

## Mundarija

1. [Talablar](#1-talablar)
2. [Server paketlari](#2-server-paketlari)
3. [MySQL bazasi](#3-mysql-bazasi)
4. [Kodni yuklab olish](#4-kodni-yuklab-olish)
5. [Backend sozlamalari (.env)](#5-backend-sozlamalari-env)
6. [Frontendni yig'ish](#6-frontendni-yigish)
7. [nginx va SSL](#7-nginx-va-ssl)
8. [Navbat ishchisi va scheduler](#8-navbat-ishchisi-va-scheduler)
9. [Bosh administrator](#9-bosh-administrator)
10. [HEMIS OAuth ulash](#10-hemis-oauth-ulash)
11. [Zoom ulash](#11-zoom-ulash)
12. [Telegram bot](#12-telegram-bot)
13. [Eski bazadan ko'chirish](#13-eski-bazadan-kochirish)
14. [Yangilanishlarni chiqarish](#14-yangilanishlarni-chiqarish)
15. [Zaxira nusxa](#15-zaxira-nusxa)
16. [Muammolarni hal qilish](#16-muammolarni-hal-qilish)

---

## 1. Talablar

| Komponent | Versiya |
|---|---|
| PHP | **8.3 yoki 8.4** (Laravel 13 talabi) — `bcmath, ctype, curl, fileinfo, intl, mbstring, mysql, openssl, pdo, tokenizer, xml, zip` kengaytmalari bilan |
| MySQL | 8.0+ (yoki MariaDB 10.11+) |
| Node.js | 20 yoki 22 (faqat frontendni yig'ish uchun) |
| Composer | 2.x |
| nginx, supervisor, cron, certbot | Ubuntu omboridan |

Domen DNS'i server IP manziliga yo'naltirilgan bo'lishi kerak. HEMIS va Telegram HTTPS talab qiladi.

## 2. Server paketlari

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip curl certbot python3-certbot-nginx \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-intl php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

php -v && composer -V && node -v
```

> Ubuntu 22.04'da PHP 8.3 bo'lmasa, avval `sudo add-apt-repository ppa:ondrej/php` qiling.

## 3. MySQL bazasi

```bash
sudo mysql
```

```sql
CREATE DATABASE mt_allpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mt_allpro'@'localhost' IDENTIFIED BY 'KUCHLI-PAROL-YOZING';
GRANT ALL PRIVILEGES ON mt_allpro.* TO 'mt_allpro'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Kodni yuklab olish

```bash
sudo mkdir -p /var/www/mt.allpro.uz
sudo chown -R $USER:www-data /var/www/mt.allpro.uz
git clone https://github.com/muxammedqudaynazarov/mt_allpro_uz.git /var/www/mt.allpro.uz
cd /var/www/mt.allpro.uz/backend
composer install --no-dev --optimize-autoloader
```

Repo yopiq bo'lsa, GitHub'da **Deploy key** (faqat o'qish huquqi bilan) qo'shib, SSH orqali klonlang. Shaxsiy tokenni serverda saqlamang.

## 5. Backend sozlamalari (.env)

```bash
cd /var/www/mt.allpro.uz/backend
cp .env.example .env
php artisan key:generate
nano .env
```

Eng muhim qiymatlar:

| O'zgaruvchi | Qiymat |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (**hech qachon `true` qilmang** — xato sahifasida maxfiy ma'lumot chiqadi) |
| `APP_URL`, `FRONTEND_URL` | `https://mt.allpro.uz` |
| `APP_LOCALE` | `kaa` (qoraqalpoqcha, asosiy til) yoki `uz` |
| `DB_*` | 3-bosqichdagi baza ma'lumotlari |
| `SANCTUM_STATEFUL_DOMAINS` | `mt.allpro.uz` |
| `SESSION_SECURE_COOKIE` | `true` |
| `HEMIS_*` | [10-bo'lim](#10-hemis-oauth-ulash) |
| `TELEGRAM_*` | [12-bo'lim](#12-telegram-bot) |

> `APP_KEY` Zoom kalitlarini shifrlaydi. Uni **yo'qotmang va o'zgartirmang**, aks holda saqlangan Zoom kalitlarini qayta kiritishga to'g'ri keladi. `.env` faylining zaxira nusxasini xavfsiz joyda saqlang.

Bazani yaratish va standart paralarni (13:00, 14:30, 16:00, 17:30, 19:00) qo'shish:

```bash
php artisan migrate --seed --force
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 6. Frontendni yig'ish

```bash
cd /var/www/mt.allpro.uz/frontend
npm ci
npm run build      # natija: frontend/dist
```

Frontend uchun `.env` kerak emas: u backend bilan bir domenda ishlaydi.

## 7. nginx va SSL

```bash
sudo cp /var/www/mt.allpro.uz/deploy/nginx/mt.allpro.uz.conf /etc/nginx/sites-available/mt.allpro.uz
sudo ln -s /etc/nginx/sites-available/mt.allpro.uz /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
```

Sertifikat olishdan oldin konfiguratsiyadagi `listen 443` blokini vaqtincha izohga oling yoki certbot'ni `--nginx` rejimida ishlating:

```bash
sudo certbot --nginx -d mt.allpro.uz
sudo nginx -t && sudo systemctl reload nginx
```

PHP 8.4 o'rnatgan bo'lsangiz, konfiguratsiyada `php8.3-fpm.sock` ni `php8.4-fpm.sock` ga almashtiring.

Tekshirish: `https://mt.allpro.uz/up` sahifasi yashil "Application up" ko'rsatishi, `https://mt.allpro.uz` esa kirish sahifasini ochishi kerak.

## 8. Navbat ishchisi va scheduler

Telegram xabarlari navbat (queue) orqali yuboriladi. Dars holatlari har daqiqada yangilanadi.

```bash
sudo cp /var/www/mt.allpro.uz/deploy/supervisor/mt-allpro-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status          # mt-allpro-worker_00  RUNNING

sudo cp /var/www/mt.allpro.uz/deploy/cron/mt-allpro /etc/cron.d/mt-allpro
sudo chmod 644 /etc/cron.d/mt-allpro
```

## 9. Bosh administrator

HEMIS ulanmagan yoki ishlamay qolgan holatda ham kirish uchun zaxira login yarating:

```bash
cd /var/www/mt.allpro.uz/backend
sudo -u www-data php artisan app:create-admin --login=admin --name="Familiya Ism" --hemis-id=1568
```

Buyruq parolni so'raydi: kamida 12 belgi, harf va raqam bo'lishi shart. `--hemis-id` ko'rsatilsa, shu xodim HEMIS orqali kirganda ham aynan shu hisobga tushadi.

Kirish sahifasida pastdagi "Administrator login va paroli bilan kirish" havolasini bosing.

## 10. HEMIS OAuth ulash

1. Universitet HEMIS administratoriga murojaat qilib, tizimni **OAuth mijozi** (client) sifatida ro'yxatdan o'tkazishni so'rang. Unga quyidagilarni bering:
   - **Redirect URI:** `https://mt.allpro.uz/auth/hemis/callback`
   - Kerakli maydonlar: `id, uuid, type, name, firstname, surname, patronymic, login, picture, email, phone, university_id`
2. Berilgan **Client ID** va **Client Secret**ni `.env`ga yozing:

   ```dotenv
   HEMIS_BASE_URL=https://hemis.karsu.uz
   HEMIS_CLIENT_ID=...
   HEMIS_CLIENT_SECRET=...
   HEMIS_REDIRECT_URI="${APP_URL}/auth/hemis/callback"
   HEMIS_UNIVERSITY_ID=        # ixtiyoriy: universitet ID'si, boshqa OTM xodimlarini to'sadi
   HEMIS_ALLOWED_TYPES=employee
   HEMIS_AUTO_REGISTER=true
   ```

3. `php artisan optimize` buyrug'ini qayta ishlating.

Xodim birinchi marta kirganda hisobi **"Tasdiq kutmoqda"** holatida yaratiladi. Administrator "Xodimlar" sahifasida uni tasdiqlaydi. `HEMIS_AUTO_REGISTER=false` qilinsa, faqat oldindan qo'shilgan (masalan, eski bazadan ko'chirilgan) xodimlar kira oladi.

> `HEMIS_BASE_URL` — universitet HEMIS **xodimlar** tizimining manzili (talabalar uchun `student.*` emas). Kirishda xatolik bo'lsa, `storage/logs/laravel-*.log` faylini tekshiring.

## 11. Zoom ulash

Har bir Zoom akkaunt (litsenziyalar to'plami) uchun bitta **Server-to-Server OAuth** ilova kerak.

1. [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth App**.
2. **Scopes** bo'limida qo'shing:
   - `meeting:write:meeting:admin`: uchrashuv yaratish
   - `meeting:read:meeting:admin`: host havolasini yangilash
   - `meeting:update:meeting:admin` (yoki `meeting:update:status:admin`): darsni yakunlash
   - `meeting:delete:meeting:admin`: boshlanmagan darsni bekor qilish
   - `user:read:list_users:admin`: Zoom foydalanuvchilari ro'yxati

   Eski ilovalarda bu ruxsatlar `meeting:write:admin`, `meeting:read:admin`, `user:read:admin` deb ataladi.
3. Ilovani **Activate** qiling. **Account ID, Client ID, Client Secret** qiymatlarini oling.
4. Tizimga bosh administrator sifatida kiring: **Sozlamalar → Zoom akkauntlar → Akkaunt qo'shish**. "Ulanishni tekshirish" tugmasi Zoom foydalanuvchilari sonini ko'rsatishi kerak.
5. **Guruhlar → Guruh qo'shish**: guruhga Zoom akkaunt va **host** (dars o'tkaziladigan Zoom foydalanuvchisi emaili, masalan `zoom_1@karsu.uz`) biriktiring.

Bitta Zoom foydalanuvchisi bir vaqtda faqat bitta uchrashuv o'tkaza oladi. Bir nechta guruh bitta hostga biriktirilsa, tizim ular uchun bir vaqtda dars yaratishga ruxsat bermaydi.

## 12. Telegram bot

Bot ixtiyoriy, lekin tavsiya etiladi: o'qituvchiga har bir dars havolasi va "Darsni boshlash" tugmasi Telegram'da keladi.

1. Telegram'da [@BotFather](https://t.me/BotFather) → `/newbot` (yoki mavjud botda `/revoke` bilan **yangi token oling**: eski token kodda ochiq qolgan edi).
2. `.env`:

   ```dotenv
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_BOT_USERNAME=dist_karsu_uz_bot
   TELEGRAM_WEBHOOK_SECRET=<openssl rand -hex 32 natijasi>
   ```

3. Webhook'ni o'rnating:

   ```bash
   php artisan optimize
   sudo -u www-data php artisan telegram:set-webhook
   ```

Xodimlar Telegram'ni **Profil → Telegram'ni ulash** tugmasi orqali o'zlari ulaydi (QR kod yoki havola).

## 13. Eski bazadan ko'chirish

Eski tizimdagi o'qituvchilar (HEMIS ID va Telegram bilan), guruhlar, Zoom kalitlari, paralar va darslar tarixi ko'chiriladi. Parollar va eski Zoom tokenlari ko'chirilmaydi.

1. Eski bazaning nusxasini oling va serverga alohida baza sifatida yuklang:

   ```bash
   mysqldump -u root -p eski_baza > old.sql
   sudo mysql -e "CREATE DATABASE mt_allpro_old CHARACTER SET utf8mb4;"
   sudo mysql mt_allpro_old < old.sql
   sudo mysql -e "GRANT SELECT ON mt_allpro_old.* TO 'mt_allpro'@'localhost';"
   ```

2. `.env`da `LEGACY_DB_DATABASE=mt_allpro_old`, `LEGACY_DB_USERNAME`, `LEGACY_DB_PASSWORD`ni to'ldiring.
3. Ko'chiring (yangi bazada guruh va darslar bo'lmasligi kerak):

   ```bash
   php artisan optimize:clear
   sudo -u www-data php artisan legacy:import
   ```

4. Natijani tekshiring:
   - **Zoom akkauntlar** sahifasida har bir akkauntga nom bering va **Client Secret'ni Zoom Marketplace'da yangilab**, yangi qiymatni kiriting.
   - **Xodimlar** sahifasida HEMIS ID'si bo'lmagan o'qituvchilarga "Rol va sozlamalar" orqali HEMIS ID kiriting. Shunda ular HEMIS orqali kirganda eski hisobiga tushadi.
   - Kerakli administratorlarga rol bering.

## 14. Yangilanishlarni chiqarish

```bash
cd /var/www/mt.allpro.uz
sudo -u www-data APP_DIR=/var/www/mt.allpro.uz ./deploy/deploy.sh
```

Skript kodni yangilaydi, frontendni qayta yig'adi, migratsiyalarni ishlatadi, keshlarni yangilaydi va navbat ishchisini qayta ishga tushiradi.

## 15. Zaxira nusxa

Kunlik baza nusxasi (cron), masalan `/etc/cron.d/mt-allpro-backup`:

```cron
30 2 * * * root mysqldump --single-transaction mt_allpro | gzip > /var/backups/mt_allpro-$(date +\%F).sql.gz && find /var/backups -name 'mt_allpro-*.sql.gz' -mtime +30 -delete
```

`backend/.env` faylini (ayniqsa `APP_KEY`ni) alohida, xavfsiz joyda saqlang.

## 16. Muammolarni hal qilish

| Belgisi | Sabab va yechim |
|---|---|
| Kirgandan keyin yana login sahifasiga qaytadi | `SANCTUM_STATEFUL_DOMAINS` domen bilan mos emas yoki `SESSION_SECURE_COOKIE=true`, lekin sayt HTTP'da ochilgan. HTTPS ishlating |
| "CSRF token mismatch" (419) | `SESSION_DOMAIN=null` va `APP_URL` haqiqiy domen bo'lishi kerak. `php artisan optimize` |
| "HEMIS bilan bog'lanib bo'lmadi" | `HEMIS_BASE_URL`, Client ID/Secret va Redirect URI'ni tekshiring. Log: `storage/logs` |
| "Zoom akkauntining kalitlari noto'g'ri" | Zoom Marketplace'da ilova faolligi va scope'larni tekshiring, Client Secret'ni qayta kiriting |
| "Zoom'da ... foydalanuvchisi topilmadi" | Guruhga biriktirilgan host email Zoom akkauntida mavjud va faol emas |
| Telegram xabarlari kelmaydi | `sudo supervisorctl status`, `storage/logs/worker.log`. Xodim Telegram'ni ulaganmi? Botni bloklamaganmi? |
| Dars holati "Efirda"dan o'zgarmaydi | Cron ishlayaptimi: `grep CRON /var/log/syslog` |
| 500 xato | `storage/logs/laravel-*.log`. Ruxsatlar: `sudo chown -R www-data:www-data storage bootstrap/cache` |
