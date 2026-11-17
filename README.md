# Masofaviy taʼlim tizimi — mt.allpro.uz

Berdaq nomidagi Qoraqalpoq davlat universitetining sirtqi va masofaviy guruhlari uchun Zoom darslarini boshqarish tizimi.

O'qituvchi HEMIS hisobi bilan kiradi, guruh va juftlikni tanlaydi. Tizim shu guruhga biriktirilgan Zoom hisobida uchrashuv yaratadi. "Darsni boshlash" va "Guruhga yuborish" tugmalari o'qituvchiga saytda va Telegram'da beriladi. Administratorlar foydalanuvchilarni tasdiqlaydi, guruhlarni boshqaradi va hisobotlarni ko'radi.

## Tarkib

| Papka | Nima |
|---|---|
| [`backend/`](backend) | **Laravel 13** (PHP 8.3+). Faqat JSON API, HEMIS OAuth, Zoom va Telegram integratsiyasi |
| [`frontend/`](frontend) | **React 19 + TypeScript + Tailwind CSS 4** (Vite). Bitta sahifali ilova (SPA) |
| [`deploy/`](deploy) | nginx, supervisor, cron sozlamalari va `deploy.sh` yangilash skripti |
| [`docs/`](docs) | O'rnatish, foydalanish va ishlab chiqish qo'llanmalari |

## Qo'llanmalar

- **[Serverga o'rnatish](docs/ORNATISH.md)** — Ubuntu serverni noldan sozlash, HEMIS/Zoom/Telegram ulash, eski bazadan ko'chirish
- **[Foydalanish qo'llanmasi](docs/FOYDALANISH.md)** — o'qituvchi, administrator va bosh administrator uchun
- **[Ishlab chiqish](docs/ISHLAB_CHIQISH.md)** — lokal muhit, testlar, API ro'yxati, arxitektura

## Qanday ishlaydi

```
 Brauzer ──► nginx (mt.allpro.uz)
              ├─ /            → frontend/dist (React)
              └─ /api /auth   → backend/public/index.php (Laravel)
                                   ├─ HEMIS OAuth   (xodim kirishi)
                                   ├─ Zoom API      (Server-to-Server OAuth)
                                   ├─ Telegram Bot  (navbat orqali xabarlar)
                                   └─ MySQL
```

Interfeys **qoraqalpoq tilida** (lotin yozuvi), o'zbek tiliga bitta tugma bilan o'tiladi. Kirish sahifasi har doim qoraqalpoqcha ochiladi; kirgan foydalanuvchining tanlovi profilda saqlanadi, Telegram xabarlari ham shu tilda keladi.

Frontend va backend bitta domenda ishlaydi, shuning uchun autentifikatsiya xavfsiz cookie-sessiya (Laravel Sanctum) orqali bo'ladi. Tokenlar brauzerning `localStorage` xotirasida saqlanmaydi va CORS sozlash kerak emas.

## Eski versiyadan nimalar o'zgardi

Tizim Laravel 10 dan Laravel 13 ga boshidan qayta yozildi. Eski versiyadagi xatolar va zaifliklar tuzatildi:

| Eski versiyada | Endi |
|---|---|
| Telegram bot tokeni, Zoom `client_secret`'lari kodda va seeder'da ochiq yozilgan | Barcha kalitlar `.env`da. Zoom kalitlari bazada **shifrlangan**, API ularni hech qachon qaytarmaydi |
| Bloklangan o'qituvchi tizimga bemalol kira olardi | Bloklangan foydalanuvchining sessiyasi darhol tugatiladi |
| Har bir o'qituvchi boshqalarning host-havolasini ko'rib, darsini boshlay olardi | Host havolasini faqat darsni yaratgan o'qituvchi oladi. Havola har safar Zoom'dan yangidan olinadi |
| Istalgan foydalanuvchi istalgan darsni tugata olardi | Darsni o'qituvchining o'zi, guruh administratori yoki bosh administrator tugatadi (Policy) |
| `/generate_token` hammaga ochiq, tokenlar tashqi cron'ga bog'liq edi | Zoom tokeni kerak bo'lganda olinadi va keshlanadi, cron kerak emas |
| Bloklash, parol tiklash va guruhni o'chirish GET so'rovlari bilan bajarilardi (CSRF) | Barcha o'zgartirishlar POST/PATCH/DELETE va CSRF himoyasi bilan |
| Parollar `uniqid()` bilan yaratilib, Telegram'ga GET URL ichida yuborilardi | O'qituvchilarda parol yo'q, ular HEMIS orqali kiradi. Zaxira login faqat adminlar uchun |
| Admin o'chirilsa, uning barcha o'qituvchilari, guruhlari va darslar tarixi o'chib ketardi (cascade) | Soft-delete qilinadi, tarix va hisobotlar saqlanadi |
| Parol o'zgartirish 500 xato berardi, o'chirish tugmasi URL'ida `}` bor edi | Ular endi yo'q: tizim HEMIS kirishiga o'tdi |
| Bir guruh/Zoom hisobida bir vaqtda ikkita dars yaratish mumkin edi | Guruh va Zoom host bandligi qulf ostida tekshiriladi |
| Tanlangan juftlik ishlatilmasdi, dars hech qachon "tugadi" holatiga o'tmasdi | Dars juftlik vaqtiga rejalashtiriladi, holat avtomatik yangilanadi |
| Telegram ID ni `@getmyid_bot` orqali qo'lda kiritish kerak edi | Xodim bitta tugma (yoki QR kod) bilan Telegram'ni ulaydi |
| Test yo'q edi | Avtomatik testlar: ruxsatlar, HEMIS, Zoom, Telegram |

Batafsil: [docs/ISHLAB_CHIQISH.md](docs/ISHLAB_CHIQISH.md#xavfsizlik).

## Tezkor ishga tushirish (lokal)

```bash
# Backend
cd backend
cp .env.example .env        # lokal uchun: APP_ENV=local, DB_CONNECTION=sqlite
composer install
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # namunaviy ma'lumot, login: admin / demo-password-2026
php artisan serve                         # http://127.0.0.1:8000

# Frontend (boshqa terminalda)
cd frontend
npm install
npm run dev                               # http://localhost:5173
```

> ⚠️ Eski repoda ochiq yozilgan Telegram bot tokeni va Zoom `client_secret`'lari git tarixida qolgan. Ularni BotFather va Zoom Marketplace'da **yangilang (rotate)**.
