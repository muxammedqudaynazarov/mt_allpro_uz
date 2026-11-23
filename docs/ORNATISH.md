# Serverga o'rnatish qo'llanmasi

Bu qo'llanma tizimni **Ubuntu 24.04** serverga noldan o'rnatishni bosqichma-bosqich ko'rsatadi. Sayt manzili **`https://mt.regofis.uz`**, kod papkasi `/mnt/projects/sites/regofis.uz/mt.regofis.uz`. Papka boshqa bo'lsa, uni hamma joyda almashtiring.

## Mundarija

1. [Talablar](#1-talablar)
2. [Server paketlari](#2-server-paketlari)
3. [MySQL bazasi](#3-mysql-bazasi)
4. [Kodni yuklab olish](#4-kodni-yuklab-olish)
5. [Backend sozlamalari (.env)](#5-backend-sozlamalari-env)
6. [Frontendni yig'ish](#6-frontendni-yigish)
7. [nginx va SSL](#7-nginx-va-ssl)
8. [Navbat ishchisi va scheduler](#8-navbat-ishchisi-va-scheduler)
9. [Administrator](#9-administrator)
10. [HEMIS OAuth ulash](#10-hemis-oauth-ulash)
11. [HEMIS talabalar API (HEMIS SYNC)](#11-hemis-talabalar-api-hemis-sync)
12. [Zoom ulash](#12-zoom-ulash)
13. [Video yozuvlar](#13-video-yozuvlar)
14. [Telegram bot](#14-telegram-bot)
15. [Ixtiyoriy: boshqa sayt ichida ochish](#15-ixtiyoriy-boshqa-sayt-ichida-ochish)
16. [Eski bazadan ko'chirish](#16-eski-bazadan-kochirish)
17. [Yangilanishlarni chiqarish](#17-yangilanishlarni-chiqarish)
18. [Zaxira nusxa](#18-zaxira-nusxa)
19. [Muammolarni hal qilish](#19-muammolarni-hal-qilish)

---

## 1. Talablar

| Komponent | Versiya |
|---|---|
| PHP | **8.3 yoki 8.4** (Laravel 13 talabi) — `bcmath, ctype, curl, fileinfo, intl, mbstring, mysql, openssl, pdo, tokenizer, xml, zip` kengaytmalari bilan (`zip` — Excel fayllari uchun) |
| MySQL | 8.0+ (yoki MariaDB 10.11+) |
| Node.js | 20 yoki 22 (faqat frontendni yig'ish uchun) |
| Composer | 2.x |
| nginx, supervisor, cron, certbot | Ubuntu omboridan |
| Disk | Video yozuvlar yoqilsa — ular uchun alohida joy ([13-bo'lim](#13-video-yozuvlar)) |

HEMIS, Zoom webhook'lari va Telegram HTTPS talab qiladi.

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
sudo mkdir -p /mnt/projects/sites/regofis.uz/mt.regofis.uz
sudo chown -R $USER:www-data /mnt/projects/sites/regofis.uz/mt.regofis.uz
git clone https://github.com/muxammedqudaynazarov/mt_allpro_uz.git /mnt/projects/sites/regofis.uz/mt.regofis.uz
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz/backend
composer install --no-dev --optimize-autoloader
```

nginx (`www-data`) fayllarni o'qiy olishi uchun yo'ldagi har bir papkada `x` huquqi bo'lishi kerak. Tekshirish: `namei -l /mnt/projects/sites/regofis.uz/mt.regofis.uz/frontend` — har qatorda oxirgi belgilar orasida `x` bo'lsin.

Repo yopiq bo'lsa, GitHub'da **Deploy key** (faqat o'qish huquqi bilan) qo'shib, SSH orqali klonlang. Shaxsiy tokenni serverda saqlamang.

## 5. Backend sozlamalari (.env)

```bash
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz/backend
cp .env.example .env
php artisan key:generate
nano .env
```

Eng muhim qiymatlar:

| O'zgaruvchi | Qiymat |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (**hech qachon `true` qilmang** — xato sahifasida maxfiy ma'lumot chiqadi) |
| `APP_URL`, `FRONTEND_URL` | Sayt manzili, ikkalasi bir xil, oxirida `/` siz: `https://mt.regofis.uz` |
| `SESSION_PATH` | `/` |
| `SESSION_COOKIE` | `karsu_zoom_session` (lotin harflaridagi cookie nomi) |
| `SESSION_SECURE_COOKIE` | `true` |
| `APP_LOCALE` | `kaa` (qoraqalpoqcha, asosiy til) yoki `uz` |
| `DB_*` | 3-bosqichdagi baza ma'lumotlari |
| `TRUSTED_PROXIES` | `127.0.0.1`. Sayt oldida boshqa serverdagi proksi bo'lsa — uning IP manzili |
| `HEMIS_*` | [10](#10-hemis-oauth-ulash) va [11-bo'lim](#11-hemis-talabalar-api-hemis-sync) |
| `RECORDINGS_*` | [13-bo'lim](#13-video-yozuvlar) |
| `TELEGRAM_*` | [14-bo'lim](#14-telegram-bot) |
| `SANCTUM_STATEFUL_DOMAINS` | Shart emas: sayt ochilgan domen avtomatik qabul qilinadi |

> `APP_KEY` Zoom kalitlarini shifrlaydi. Uni **yo'qotmang va o'zgartirmang**, aks holda saqlangan Zoom kalitlarini qayta kiritishga to'g'ri keladi. `.env` faylining zaxira nusxasini xavfsiz joyda saqlang.

Bazani yaratish va standart juftliklarni (13:00, 14:30, 16:00, 17:30, 19:00) qo'shish:

```bash
php artisan migrate --seed --force
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 6. Frontendni yig'ish

```bash
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz/frontend
npm ci
npm run build      # natija: frontend/dist
```

Frontend uchun `.env` kerak emas: u backend bilan bir domenda ishlaydi. Keyingi yangilanishlarni `deploy/deploy.sh` o'zi yig'adi ([17-bo'lim](#17-yangilanishlarni-chiqarish)).

## 7. nginx va SSL

Avval mt.regofis.uz uchun guvohnoma oling (nginx standart sayti yoqiq turgan holda):

```bash
sudo certbot certonly --nginx -d mt.regofis.uz
```

Keyin tizim konfiguratsiyasini o'rnating:

```bash
sudo cp /mnt/projects/sites/regofis.uz/mt.regofis.uz/deploy/nginx/mt.regofis.uz.conf /etc/nginx/sites-available/mt.regofis.uz
sudo ln -sf /etc/nginx/sites-available/mt.regofis.uz /etc/nginx/sites-enabled/mt.regofis.uz
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Guvohnoma sotib olingan bo'lsa (certbot emas), konfiguratsiyadagi `ssl_certificate` va `ssl_certificate_key` yo'llarini almashtiring. PHP 8.4 o'rnatilgan bo'lsa, `php8.3-fpm.sock` ni `php8.4-fpm.sock` ga almashtiring.

Konfiguratsiya qiladigan ishlar:
- `/api`, `/auth`, `/sanctum`, `/l`, `/up` so'rovlarini Laravel'ga beradi, qolgan barcha manzillarni React'ga;
- xavfsizlik sarlavhalarini qo'shadi (CSP, HSTS va boshqalar);
- video yozuvlarni ichki `/_zoom_recordings/` manzili orqali beradi ([13-bo'lim](#13-video-yozuvlar)).

> Serverda avvalgi versiyaning konfiguratsiyasi turgan bo'lsa, uni shu fayl bilan almashtiring: unda video uchun yangi `location /_zoom_recordings/` bloki va CSP'da `media-src` qo'shilgan.

### Tekshirish

`https://mt.regofis.uz/up` — yashil "Application up", `https://mt.regofis.uz` — kirish sahifasi. Keyin:

```bash
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz/backend
sudo -u www-data php artisan app:check --url=https://mt.regofis.uz
```

Buyruq domen, cookie, frontend yig'ilgan yo'l, baza, papka huquqlari, HEMIS, Zoom va Telegram'ni tekshiradi va har bir muammo uchun nima qilishni yozadi. `.env` o'zgartirilgandan keyin har safar `sudo -u www-data php artisan optimize` ishga tushiring — aks holda eski sozlamalar keshda qoladi.

## 8. Navbat ishchisi va scheduler

```bash
sudo cp /mnt/projects/sites/regofis.uz/mt.regofis.uz/deploy/supervisor/mt-allpro-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status          # mt-allpro-worker_00  RUNNING

sudo cp /mnt/projects/sites/regofis.uz/mt.regofis.uz/deploy/cron/mt-allpro /etc/cron.d/mt-allpro
sudo chmod 644 /etc/cron.d/mt-allpro
```

Navbat ishchisi Telegram xabarlarini yuboradi. Cron har daqiqada scheduler'ni ishga tushiradi:

| Buyruq | Qachon | Nima qiladi |
|---|---|---|
| `lessons:sync-statuses` | har daqiqa | Dars holatlari (rejada → efirda → yakunlangan) |
| `lessons:track-zoom` | har daqiqa | Dars Zoom'da haqiqatda boshlandimi, tugadimi; tugagan darslarning qatnashchilar hisoboti |
| `lessons:materialize-series` | har daqiqa | Haftalik darslarni dars kuni, boshlanishidan **1 soat oldin** yaratib, Telegram'ga yuboradi |
| `recordings:process` | har 5 daqiqa | Video yozuvlarni Zoom'dan serverga ko'chiradi va Zoom'dan o'chiradi |
| `model:prune` | har kuni | Eski jonli qatnashuv yozuvlari (7 kun) va amallar tarixi (2 yil) tozalanadi |

## 9. Administrator

HEMIS ulanmagan yoki ishlamay qolgan holatda ham kirish uchun zaxira login yarating:

```bash
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz/backend
sudo -u www-data php artisan app:create-admin --login=admin --name="Familiya Ism" --hemis-id=1568
```

Buyruq parolni so'raydi: kamida 12 belgi, harf va raqam bo'lishi shart. `--hemis-id` ko'rsatilsa, shu xodim HEMIS orqali kirganda ham aynan shu hisobga tushadi. Kirish sahifasida HEMIS tugmasi ostidagi **"Login orqali kirish"** tugmasini bosing.

### Himoyalangan administrator

Tizim egasining HEMIS ID sini `.env` ga yozing (bir nechta bo'lsa — vergul bilan):

```dotenv
HEMIS_ADMIN_IDS=1574
```

Keyin `sudo -u www-data php artisan optimize`. Shu HEMIS ID egasi:
- har safar kirganda (HEMIS yoki zaxira login orqali) Administrator roli, faol holati va — o'chirilgan bo'lsa — hisobi tiklanadi;
- saytda boshqa administratorlar uning Administrator rolini olib tashlay, bloklay, o'chira yoki HEMIS ID sini o'zgartira olmaydi (Foydalanuvchilar ro'yxatida qulf belgisi bilan ko'rsatiladi).

Himoyani faqat serverdagi `.env` orqali olib tashlash mumkin. `php artisan app:check` himoyalangan administrator holatini ko'rsatadi.

Tizimda uchta rol bor: **Administrator** (hammasi), **O'quv bo'limi** (barcha darslar, monitoring, hisobotlar; dars yaratmaydi, Zoom va sozlamalarni o'zgartirmaydi), **O'qituvchi**. Rollar **Foydalanuvchilar → ⋯ → Rol va sozlamalar** da beriladi.

## 10. HEMIS OAuth ulash

1. Universitet HEMIS administratoriga murojaat qilib, tizimni **OAuth mijozi** (client) sifatida ro'yxatdan o'tkazishni so'rang. Unga quyidagilarni bering:
   - **Redirect URI:** `https://mt.regofis.uz/auth/hemis/callback` (aniq qiymatni `php artisan app:check` ham ko'rsatadi)
   - Kerakli maydonlar: `id, type, name, firstname, surname, patronymic, picture, login, university_id`
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

3. `sudo -u www-data php artisan optimize` buyrug'ini qayta ishlating.

Bazada xodimdan faqat **familiya, ism, otasining ismi, HEMIS ID va rasm havolasi** saqlanadi. `type`, `login`, `university_id` faqat kirishda tekshirish uchun o'qiladi va saqlanmaydi. Telefon, email va boshqa ma'lumotlar so'ralmaydi.

Xodim birinchi marta kirganda hisobi **"Tasdiq kutmoqda"** holatida yaratiladi, administrator uni tasdiqlaydi. `HEMIS_AUTO_REGISTER=false` qilinsa, faqat oldindan qo'shilgan xodimlar kira oladi.

> `HEMIS_BASE_URL` — universitet HEMIS **xodimlar** tizimining manzili (talabalar uchun `student.*` emas). Kirishda xatolik bo'lsa, `storage/logs/laravel-*.log` faylini tekshiring.

## 11. HEMIS talabalar API (HEMIS SYNC)

**Guruhlar → guruh → Talabalar** sahifasida har bir talabaga HEMIS ID kiritiladi, **HEMIS SYNC** tugmasi esa HEMIS'dan haqiqiy F.I.Sh. va rasmni oladi — faqat yangi kiritilgan yoki o'zgargan ID'lar uchun. Buning uchun talabalar HEMIS'ining REST API tokeni kerak (`https://student.karsu.uz/rest/docs`):

```dotenv
HEMIS_API_URL=https://student.karsu.uz/rest
HEMIS_API_TOKEN=...
HEMIS_STUDENT_ENDPOINT=v1/data/student-list
HEMIS_STUDENT_SEARCH_PARAM=search
```

`.env` dan keyin `php artisan optimize`. Bitta talabada tekshiring:

```bash
sudo -u www-data php artisan hemis:student 123456789012
```

Buyruq javob kodini, topilgan yozuvlar sonini, javobdagi maydon nomlarini va topilgan ism-familiyani ko'rsatadi. Talaba topilmasa yoki javob tuzilmasi boshqacha bo'lsa, swagger sahifasidagi talabalar ro'yxati endpointi va qidiruv parametri nomini tekshirib, `HEMIS_STUDENT_ENDPOINT` / `HEMIS_STUDENT_SEARCH_PARAM` ni moslang.

Talabadan faqat **F.I.Sh., HEMIS ID va rasm havolasi** saqlanadi.

## 12. Zoom ulash

Har bir Zoom akkaunt (litsenziyalar to'plami) uchun bitta **Server-to-Server OAuth** ilova kerak.

### 12.1. Ilova va ruxsatlar (Scopes)

1. [marketplace.zoom.us](https://marketplace.zoom.us) → **Develop → Build App → Server-to-Server OAuth App**.
2. **Scopes** bo'limida qo'shing:

| Ruxsat | Nima uchun |
|---|---|
| `user:read:list_users:admin` | Zoom foydalanuvchilari (hostlar) ro'yxati |
| `user:update:user:admin` | Darsni boshlashda host ismini o'qituvchi nomiga o'zgartirish ("QUDAYNAZAROV M.S."). Bo'lmasa, dars baribir boshlanadi — ism o'zgarmaydi |
| `meeting:write:meeting:admin` | Dars uchun uchrashuv yaratish |
| `meeting:read:meeting:admin` | Host havolasini yangilash, holatni kuzatish |
| `meeting:update:status:admin` | Darsni yakunlash |
| `meeting:delete:meeting:admin` | Boshlanmagan darsni bekor qilish |
| `meeting:read:list_past_instances:admin` | O'tgan uchrashuvlar |
| `meeting:read:past_meeting:admin` | Darsning aniq boshlanish/tugash vaqti |
| `report:read:list_meeting_participants:admin` | Dars tugagach qatnashchilar va kirib-chiqish vaqtlari (**Pro va yuqori**) |
| `meeting:read:list_past_participants:admin` | Zaxira: hisobot ruxsati bo'lmasa |
| `dashboard:read:list_meeting_participants:admin` | Zaxira: webhook kelmaganda jonli qatnashchilar (**Business va yuqori**) |
| `cloud_recording:read:list_recording_files:admin` | Video yozuv fayllari ro'yxati |
| `cloud_recording:delete:meeting_recording:admin` | Serverga ko'chirilgan yozuvni Zoom'dan o'chirish |

   Eski ilovalarda ruxsatlar `meeting:write:admin`, `meeting:read:admin`, `user:read:admin`, `report:read:admin`, `recording:read:admin`, `recording:write:admin`, `dashboard_meetings:read:admin` deb ataladi.
3. Ilovani **Activate** qiling. **Account ID, Client ID, Client Secret** ni oling.
4. Tizimda: **Zoom akkauntlar → Akkaunt qo'shish** → **Ulanishni tekshirish** Zoom foydalanuvchilari sonini ko'rsatishi kerak. Ruxsat keyinroq qo'shilsa ham shu tugmani bosing — yangi ruxsatlar bilan token darhol olinadi.
5. Akkaunt ostidagi **Zoom hostlar → Zoom'dan yangilash**: akkauntdagi foydalanuvchilar host sifatida qo'shiladi. Basic (litsenziyasiz) hostlar nofaol qo'shiladi: ularda uchrashuv 40 daqiqa bilan cheklangan va bulutga yozib bo'lmaydi.
6. **Guruhlar → Guruh qo'shish**: guruhga Zoom akkaunt va (ixtiyoriy) standart host biriktiring. O'qituvchi dars yaratishda bo'sh hostni o'zi tanlaydi — bir host bir vaqtda faqat bitta dars o'tkaza oladi.

### 12.2. Event Subscriptions (webhook)

Jonli qatnashchilar ro'yxati (Monitoring) va video yozuvlar webhook orqali keladi. Shu ilovaning o'zida:

1. **Feature → Event Subscriptions** ni yoqing → **Add Event Subscription**.
2. **Event notification endpoint URL** — tizimdagi **Zoom akkauntlar** sahifasida har bir akkaunt uchun ko'rsatilgan manzil, masalan `https://mt.regofis.uz/api/zoom/webhook/1` (**Nusxalash** tugmasi).
3. Hodisalar:
   - **Meeting:** `meeting.started`, `meeting.ended`, `meeting.participant_joined`, `meeting.participant_left`
   - **Recording:** `recording.completed`
4. Sahifadagi **Secret Token** ni nusxalab, tizimda **Zoom akkauntlar → Tahrirlash → Webhook Secret Token** ga kiriting va saqlang.
5. Shundan **keyin** Zoom'da **Validate** tugmasini bosing — tizim Zoom'ning tekshiruv so'roviga javob beradi. Saqlang.

Ishlayotganini **Zoom akkauntlar** sahifasidagi "Webhook: Ishlayapti, oxirgi hodisa: ..." yozuvidan bilasiz. Imzosi noto'g'ri yoki 5 daqiqadan eski so'rovlar rad etiladi.

### 12.3. Talabalar uchun tavsiya etiladigan Zoom sozlamalari

Uchrashuv ichidagi ruxsatlar Zoom akkaunt sozlamalaridan olinadi. Zoom saytida akkaunt egasi sifatida: **Admin → Account Management → Account Settings → Meeting**:

| Sozlama | Qiymat | Nima uchun |
|---|---|---|
| Allow participants to rename themselves | **o'chiq** 🔒 | Talaba dars o'rtasida ismini o'zgartira olmaydi — davomat aniqroq |
| Start Video / Allow participants to start video | **o'chiq** 🔒 | Talabalar kamerani yoqmaydi |
| Participants video | **off** | Talabalar kamerasi o'chiq holda kiradi |
| Embed passcode in invite link for one-click join | **yoqilgan** | Havolani bosgan talaba kod kiritmaydi |
| Waiting room | **o'chiq** | Talabalar darhol kiradi |

🔒 — sozlamani qulflang (lock), shunda barcha hostlarga qo'llanadi.

### 12.4. Kuzatuv va davomat qanday ishlaydi

- Webhook'lar dars boshlangani, tugagani va har bir qatnashchining kirib-chiqishini darhol yetkazadi. **Monitoring** sahifasi ro'yxatni har 30 soniyada yangilaydi.
- `lessons:track-zoom` har daqiqada holatni Zoom API'dan ham tekshiradi (webhook kelmay qolsa ham dars holati to'g'ri bo'ladi).
- Dars tugagach (3 daqiqadan keyin) Zoom hisobotidan har bir qatnashchining aniq vaqtlari olinadi; tayyor bo'lmasa 10, 30, 60, 180 daqiqadan keyin qayta urinadi. Zoom hisobotlari **taxminan 6 oy** saqlanadi — shu muddat ichida dars sahifasidagi **"Zoom hisobotini yangilash"** bilan qayta olish mumkin.

Talabalar tizimga kirmaydi: ular Zoom'dagi ismi bo'yicha guruh ichida aniqlanadi (kirill/lotin, so'zlar tartibi, "(iPhone)" kabi qo'shimchalar farq qilmaydi).

## 13. Video yozuvlar

Yoqilganda darslar Zoom bulutiga yoziladi, keyin tizim ularni serverga ko'chiradi, hajmini tekshiradi va shundan keyingina Zoom'dan o'chiradi.

1. **Zoom'da:** Admin → Account Settings → **Recording → Cloud recording** yoqilgan bo'lsin. Bulutga faqat **Licensed** hostlar yoza oladi. 12.1 dagi `cloud_recording:*` ruxsatlari va 12.2 dagi `recording.completed` hodisasi kerak.
2. **Papka:** yozuvlar `backend/storage/app/recordings` da saqlanadi (`/mnt/projects/sites/regofis.uz/mt.regofis.uz/backend/storage/app/recordings`). Papka o'zi yaratiladi va git'ga kirmaydi — `git pull` unga tegmaydi. Joyni baholash: 80 daqiqalik dars videosi odatda bir necha yuz MB; `df -h /mnt/projects` bilan bo'sh joyni kuzating.
3. **.env:**

   ```dotenv
   RECORDINGS_ACCEL_PREFIX=/_zoom_recordings/  # videoni nginx beradi
   RECORDINGS_MIN_FREE_GB=5                    # ixtiyoriy: bo'sh joy shundan kam bo'lsa, yangi yozuvlar yuklanmaydi
   ```

   `/_zoom_recordings/` — papka emas, nginx'ning ichki manzili (`mt.regofis.uz.conf` dagi `location /_zoom_recordings/`, u `storage/app/recordings` ga qaraydi). Brauzer videoni `/api/recordings/{id}/file` dan so'raydi: Laravel ruxsatni tekshiradi, faylni nginx beradi — video ijro va o'rash (seek) tez ishlaydi, PHP band bo'lmaydi. Bu qator bo'lmasa, fayl PHP orqali beriladi (ishlaydi, lekin sekinroq).

   Yozuvlarni boshqa diskda saqlash kerak bo'lsa: `RECORDINGS_PATH=<to'liq yo'l>` va nginx'dagi `alias` ni shu yo'lga almashtiring.
4. **Tizimda:** **Umumiy sozlamalar → Video yozuvlar → Darslarni avtomatik yozib olish** ni yoqing. Yoqilgandan keyin **yangi yaratilgan** darslar yoziladi.

Zoom'dan o'chirish usuli shu sahifada tanlanadi: **Savatga** (tavsiya etiladi; 30 kun ichida Zoom'da tiklash mumkin, savatdagi fayllar bulut hajmiga hisoblanmaydi) yoki **Butunlay**. Fayl serverga to'liq ko'chib, hajmi Zoom'dagi bilan mos kelmaguncha Zoom'dan hech narsa o'chirilmaydi. Yuklash xato bersa, 5 martagacha qayta urinadi; **Video yozuvlar** sahifasida **Qayta urinish** tugmasi bor.

Yozuvlar MP4 (H.264/AAC) formatida — brauzerda dars sahifasining o'zida ijro etiladi va yuklab olinadi. Ularni Administrator, O'quv bo'limi va darsning o'qituvchisi ko'radi.

## 14. Telegram bot

Bot ixtiyoriy, lekin tavsiya etiladi: o'qituvchiga har bir dars havolasi va "Darsni boshlash" tugmasi Telegram'da keladi (haftalik darslar — dars kuni, 1 soat oldin).

1. Telegram'da [@BotFather](https://t.me/BotFather) → `/newbot` (yoki mavjud botda `/revoke` bilan **yangi token oling**: eski token kodda ochiq qolgan edi).
2. `.env`:

   ```dotenv
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_BOT_USERNAME=dist_karsu_uz_bot
   TELEGRAM_WEBHOOK_SECRET=<openssl rand -hex 32 natijasi>
   ```

3. Webhook'ni o'rnating (manzil `APP_URL` dan olinadi: `https://mt.regofis.uz/api/telegram/webhook`):

   ```bash
   sudo -u www-data php artisan optimize
   sudo -u www-data php artisan telegram:set-webhook
   ```

Xodimlar Telegram'ni **Profil → Telegram'ni ulash** tugmasi orqali o'zlari ulaydi (QR kod yoki havola).

## 15. Ixtiyoriy: boshqa sayt ichida ochish

KarSU uchun kerak emas: asosiy manzil `https://mt.regofis.uz`. Kelajakda tizimni boshqa sayt ichida, yo'lda ochish kerak bo'lsa (masalan, `https://dist.karsu.uz/zoom`), kod buni qo'llaydi. Konfiguratsiyalar `deploy/nginx/yolda-ishlash/` papkasida:

| Fayl | Qachon |
|---|---|
| `bir-serverda.conf` | Tizim o'sha sayt bilan bir serverda: bloklar o'sha saytning `server` bloki ichiga qo'shiladi |
| `proksi.conf` + `proksi-manba.conf` | Tizim mt.regofis.uz da qoladi, o'sha sayt unga proksi qiladi. mt.regofis.uz ga to'g'ridan-to'g'ri kirganlar yangi manzilga yo'naltiriladi |

O'zgartiriladiganlar:
1. `.env`: `APP_URL` va `FRONTEND_URL` yangi manzil (masalan, `https://dist.karsu.uz/zoom`), `SESSION_PATH=/zoom`. Proksi bo'lsa — `TRUSTED_PROXIES=<proksi serverining IP manzili>`: noto'g'ri bo'lsa, `app:check` to'g'ri IP'ni ko'rsatadi.
2. `deploy.sh` — frontend yangi yo'l uchun o'zi qayta yig'iladi.
3. HEMIS'da yangi Redirect URI, `php artisan telegram:set-webhook`, Zoom ilovalarida yangi webhook manzili va **Validate**.
4. `php artisan app:check --url=<yangi manzil>`.

## 16. Eski bazadan ko'chirish

Eski tizimdagi o'qituvchilar (HEMIS ID va Telegram bilan), guruhlar, Zoom kalitlari, juftliklar va darslar tarixi ko'chiriladi. Parollar va eski Zoom tokenlari ko'chirilmaydi.

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
   - **Foydalanuvchilar** sahifasida HEMIS ID'si bo'lmagan o'qituvchilarga "Rol va sozlamalar" orqali HEMIS ID kiriting.
   - **O'quv yillari** sahifasida o'quv yili va semestr sanalarini kiriting — eski darslar sanasiga qarab semestrlarga biriktiriladi.

## 17. Yangilanishlarni chiqarish

```bash
cd /mnt/projects/sites/regofis.uz/mt.regofis.uz
sudo ./deploy/deploy.sh
```

Skript kodni yangilaydi, frontendni qayta yig'adi, `composer install`, migratsiyalar, keshlarni yangilaydi va navbat ishchisini qayta ishga tushiradi. git, npm va composer **kod papkasi egasi** nomidan ishlaydi, `php artisan` — `www-data` nomidan. Shuning uchun `sudo -u www-data ./deploy/deploy.sh` qilmang: git "dubious ownership" deb to'xtaydi. Yangilash davomida sayt texnik ishlar rejimida bo'ladi va xato chiqsa ham oxirida ochiladi.

## 18. Zaxira nusxa

Kunlik baza nusxasi (cron), masalan `/etc/cron.d/mt-allpro-backup`:

```cron
30 2 * * * root mysqldump --single-transaction mt_allpro | gzip > /var/backups/mt_allpro-$(date +\%F).sql.gz && find /var/backups -name 'mt_allpro-*.sql.gz' -mtime +30 -delete
```

`backend/.env` faylini (ayniqsa `APP_KEY`ni) alohida, xavfsiz joyda saqlang. Video yozuvlar Zoom'dan o'chirilgach yagona nusxa serverda qoladi — `backend/storage/app/recordings` ni ham zaxiralang (masalan, `rsync` bilan boshqa diskka) yoki Zoom'dan **Savatga** o'chirish rejimini qoldiring.

## 19. Muammolarni hal qilish

Avval `sudo -u www-data php artisan app:check --url=https://mt.regofis.uz` ni ishga tushiring — ko'p muammolarni o'zi topadi.

| Belgisi | Sabab va yechim |
|---|---|
| Kirgandan keyin yana login sahifasiga qaytadi | `SESSION_PATH` sayt yo'liga mos emas, yoki `SESSION_SECURE_COOKIE=true`, lekin sayt HTTP'da |
| "CSRF token mismatch" (419) | `SESSION_DOMAIN=null`, `APP_URL` haqiqiy manzil, keyin `php artisan optimize` |
| "HEMIS bilan bog'lanib bo'lmadi" yoki HEMIS "redirect_uri" xatosi | HEMIS'da ro'yxatdan o'tgan Redirect URI `app:check` ko'rsatgan bilan bir xil emas |
| "HEMIS orqali kirish hali sozlanmagan" | `HEMIS_CLIENT_ID`/`HEMIS_CLIENT_SECRET` bo'sh yoki `php artisan optimize` qilinmagan |
| HEMIS SYNC: "topilmadi" hammasida | `php artisan hemis:student <ID>` bilan tekshiring, endpoint va parametr nomini moslang (11-bo'lim) |
| Davomat chiqmaydi | Dars sahifasidagi xato matnini o'qing, masalan `does not contain scopes:[report:read:list_meeting_participants:admin]` — shu ruxsatni qo'shing. Bepul tarifda hisobot API ishlamaydi |
| Monitoring'da qatnashchilar ko'rinmaydi | Zoom akkauntlar sahifasida "Webhook: Sozlanmagan" yoki "hodisa kelmagan" — 12.2 ni tekshiring |
| Video yozuvlar paydo bo'lmaydi | Sozlamalarda yozish yoqilganmi (faqat yangi darslar), host Licensed'mi, `recording.completed` hodisasi va `cloud_recording` ruxsatlari bormi. Xato matni **Video yozuvlar** sahifasida |
| Video "Navbatda"dan o'tmaydi | Cron ishlayaptimi, `RECORDINGS_PATH` ga `www-data` yoza oladimi, bo'sh joy `RECORDINGS_MIN_FREE_GB` dan ko'pmi |
| Video bo'sh (0:00), yuklab olingan fayl ~1 KB | `RECORDINGS_ACCEL_PREFIX` yoqilgan, lekin nginx'da `location /_zoom_recordings/` yo'q yoki `alias` papkasi boshqa — nginx video o'rniga sayt sahifasini beradi. `app:check` buni tekshiradi va qo'shiladigan blokni ko'rsatadi |
| "Zoom akkauntining kalitlari noto'g'ri" | Zoom Marketplace'da ilova faolligi va scope'larni tekshiring, Client Secret'ni qayta kiriting |
| Haftalik dars yaratilmadi | Kalendar'da sababi yoziladi (masalan, bo'sh host yo'q). O'qituvchiga Telegram'da xabar boradi |
| Telegram xabarlari kelmaydi | `sudo supervisorctl status`, `storage/logs/worker.log`. Xodim Telegram'ni ulaganmi? |
| Dars holati "Efirda"dan o'zgarmaydi | Cron ishlayaptimi: `grep CRON /var/log/syslog` |
| Kirishda "Server Error" yoki 500 | `tail -n 60 storage/logs/laravel-$(date +%F).log`. Ko'pincha huquqlar: `sudo chown -R www-data:www-data storage bootstrap/cache` |
