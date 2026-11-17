# Ishlab chiqish qo'llanmasi

## Lokal muhit

Talablar: PHP 8.3+, Composer 2, Node.js 20+. Lokal ishlash uchun MySQL shart emas, SQLite yetarli.

```bash
# 1. Backend
cd backend
cp .env.example .env
```

`.env`da lokal uchun quyidagilarni o'zgartiring:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=sqlite
# DB_DATABASE ni o'chiring yoki izohga oling — database/database.sqlite ishlatiladi
SESSION_SECURE_COOKIE=false
SESSION_ENCRYPT=false
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
LOG_LEVEL=debug
```

```bash
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder
php artisan serve                 # http://127.0.0.1:8000

# 2. Frontend (alohida terminal)
cd frontend
npm install
npm run dev                       # http://localhost:5173
```

Vite `/api`, `/auth`, `/sanctum` so'rovlarini Laravel'ga proksi qiladi (`frontend/vite.config.ts`). Brauzer uchun hammasi bitta manzilda bo'ladi, xuddi production'dagidek.

`DemoSeeder` namunaviy xodimlar, guruhlar va 30 kunlik dars tarixini yaratadi. Kirish: **admin / demo-password-2026**. Demo Zoom kalitlari soxta, shuning uchun lokal muhitda haqiqiy dars yaratish uchun haqiqiy Zoom akkaunt qo'shing.

## Testlar

```bash
cd backend
php artisan test                  # yoki: composer test
```

Testlar SQLite xotirasida ishlaydi. Tashqi xizmatlarga so'rov ketmaydi: `Http::preventStrayRequests()` yoqilgan, Zoom, HEMIS va Telegram javoblari soxtalashtirilgan. Asosiy qamrov:

- `HemisLoginTest`: OAuth state tekshiruvi, yangi xodim "kutish" holatida yaratilishi, bloklangan, o'chirilgan, talaba va boshqa OTM xodimlari rad etilishi, eski xodimni HEMIS login orqali topish
- `SessionAndStatusTest`: zaxira login faqat adminlar uchun, rate limit, bloklangan sessiya tugatilishi, xavfsizlik sarlavhalari
- `LessonTest`: dars yaratish (juftlik, "hozir", boshlangan juftlik), guruh va host bandligi, Zoom xatolari, host havolasi faqat egasiga, imzolangan Telegram havolasi, CSV eksport (formula injection himoyasi)
- `AdministrationTest`: rollar bo'yicha ruxsatlar, Zoom secret shifrlanishi, soft-delete, hisobotlar, amallar jurnali
- `TelegramTest`: webhook maxfiy kaliti, bir martalik ulash havolasi
- `AttendanceTest`: Zoom holatini kuzatish (boshlandi, host tugatdi, o'tkazilmagan), hisobotdan davomat (ustma-ust vaqt bir marta, kutish zali va host hisobga olinmaydi), talabani ism va Zoom ID bo'yicha tanish, ruxsat yo'q bo'lsa `past_meetings` zaxirasi, qayta urinish, birlashtirish, "talaba emas", hisobot ko'rsatkichlari
- `DeploymentTest`: o'z domenida login, `app:check`
- `Unit/StudentNameTest`: ism kaliti (kirill/lotin, tartib, qurilma nomlari), vaqt oraliqlarini birlashtirish

Frontend tekshiruvi:

```bash
cd frontend
npm run typecheck
npm run build
```

Kod uslubi (backend): `vendor/bin/pint`.

## Tuzilma

```
backend/
  app/
    Actions/            CreateLesson, EndLesson — asosiy biznes-mantiq
    Console/Commands/   lessons:sync-statuses, lessons:track-zoom, app:create-admin, app:check, telegram:set-webhook, legacy:import
    Enums/              Role, UserStatus, LessonStatus
    Exceptions/         ExternalServiceException (Zoom/HEMIS xatolari → tushunarli 502/422)
    Http/
      Controllers/Api/  JSON API
      Controllers/Auth/ HemisController (OAuth), SessionController (zaxira login)
      Middleware/       EnsureAccountStatus, EnsureRole, SecurityHeaders
      Resources/        API javob formatlari
    Jobs/               SendTelegramMessage (navbat orqali)
    Models/             User, Group, ZoomAccount, LessonSlot, Lesson, AuditLog
    Policies/           LessonPolicy, GroupPolicy, UserPolicy
    Services/           Hemis/HemisOAuth, Zoom/ZoomClient, Telegram/TelegramBot
    Support/            Audit (jurnal), Notifier (Telegram matnlari)
  routes/api.php        barcha API marshrutlari
  routes/web.php        HEMIS OAuth va Telegram "Darsni boshlash" havolasi
  routes/console.php    scheduler

frontend/src/
  auth/                 AuthProvider (sessiya, rollar), marshrut himoyasi
  components/ui/        Button, Field, Modal, Badge, Segmented, ...
  components/layout/    AppShell (yon menyu)
  features/lessons/     dars qatori, "Efirda" paneli, amallar
  pages/                sahifalar
  lib/                  api (axios + CSRF), format (sana/vaqt, Toshkent), types
```

## Ma'lumotlar modeli

| Jadval | Tavsif |
|---|---|
| `users` | Foydalanuvchilar. `roles` (JSON: teacher/admin/super_admin), `status` (pending/active/blocked), HEMIS maydonlari, `telegram_chat_id`, `managed_by_id` (masʼul admin). Soft-delete |
| `zoom_accounts` | Server-to-Server OAuth kalitlari. `client_secret` shifrlangan |
| `groups` | Guruh → Zoom akkaunt + host email, masʼul admin, faollik. Soft-delete |
| `lesson_slots` | Juftliklar: boshlanish vaqti, davomiylik. Standart nomlar (`1-juplıq`) ekranda tilga moslab ko'rsatiladi (`label`) |
| `lessons` | Darslar: o'qituvchi, guruh, Zoom meeting ID, havolalar (`start_url` shifrlangan), holat, vaqt |
| `audit_logs` | Amallar jurnali (2 yildan keyin avtomatik tozalanadi) |
| `lessons.zoom_*` | Zoom'dagi haqiqiy holat: `zoom_state` (waiting/started/ended/missed), `zoom_started_at`, `zoom_ended_at`, `zoom_minutes`; `attendance_*` — hisobotni olish holati |
| `students` | Guruh talabalari (Zoom'dagi ism bo'yicha avtomatik yaratiladi), `is_ignored` |
| `student_aliases` | Talabani tanish kalitlari: `name` (`StudentName::key()`), `zoom_id`, `email`. Guruh ichida yagona |
| `zoom_participations` | Zoom hisobotidagi har bir kirish-chiqish (xom ma'lumot; birlashtirishda davomat shundan qayta hisoblanadi) |
| `lesson_attendances` | Dars bo'yicha talabaning yakuniy qatnashuvi: jami soniya (ustma-ust vaqt bir marta), kechikish, kirishlar soni |

Zoom'dagi haqiqiy holatni `lessons:track-zoom` (har daqiqa) kuzatadi: `GET /meetings/{id}` faqat `waiting`/`started` qaytaradi, `started` → `waiting` o'tishi host tugatganini bildiradi (`MeetingTracker`). Tugagan dars uchun `SyncLessonAttendance` Zoom'dan uchrashuv nusxalari (`/past_meetings/{id}/instances`), aniq vaqtlar (`/past_meetings/{uuid}`) va qatnashchilarni (`/report/meetings/{uuid}/participants`, ruxsat bo'lmasa `/past_meetings/{uuid}/participants`) oladi.

Dars holati bazada saqlanadi va har daqiqada `lessons:sync-statuses` yangilaydi. API esa holatni vaqtdan qo'shimcha hisoblaydi (`Lesson::effectiveStatus()`), shuning uchun scheduler to'xtab qolsa ham foydalanuvchi to'g'ri holatni ko'radi.

## API

Barcha `/api/*` marshrutlari JSON qaytaradi. Autentifikatsiya: Sanctum SPA (cookie sessiya + `X-XSRF-TOKEN`). O'zgartiruvchi so'rovlardan oldin frontend `GET /sanctum/csrf-cookie` chaqiradi.

| Metod | Manzil | Kim | Tavsif |
|---|---|---|---|
| GET | `/auth/hemis/redirect` | mehmon | HEMIS'ga yo'naltirish |
| GET | `/auth/hemis/callback` | mehmon | HEMIS qaytish nuqtasi |
| GET | `/api/config` | hamma | Ochiq sozlamalar (HEMIS/Telegram yoqilganmi) |
| POST | `/api/auth/login` | mehmon | Zaxira login (faqat adminlar) |
| POST | `/api/auth/logout` | kirgan | Chiqish |
| GET | `/api/me` | kirgan | Joriy foydalanuvchi, rollar, ruxsatlar |
| POST/DELETE | `/api/me/telegram` | kirgan | Telegram ulash havolasi / uzish |
| GET | `/api/dashboard` | faol | Efirdagi va bugungi darslar, statistika |
| GET | `/api/lessons` | faol | Darslar (filtr: status, group_id, mine, from, to) |
| GET | `/api/lessons/options` | faol | Dars yaratish uchun guruhlar (band/bo'sh) va juftliklar |
| POST | `/api/lessons` | faol | Dars yaratish `{group_id, slot_id?, topic?}` |
| POST | `/api/lessons/{id}/start` | egasi | Yangi host havolasi |
| POST | `/api/lessons/{id}/end` | egasi, guruh admini | Yakunlash / bekor qilish |
| GET | `/api/lessons/export` | admin | CSV |
| GET | `/api/lessons/{id}/attendance` | dars egasi, guruh admini | Zoom vaqtlari, host va talabalar (vaqt oraliqlari bilan) |
| GET | `/api/lessons/{id}/attendance/export` | dars egasi, guruh admini | Davomat CSV |
| POST | `/api/lessons/{id}/attendance/sync` | dars egasi, guruh admini | Zoom hisobotini hozir qayta olish |
| GET | `/api/groups/{id}/students?from&to` | guruh admini | Talabalar va davr bo'yicha davomat |
| POST | `/api/groups/{id}/students/merge` | guruh admini | `{target_id, source_ids[]}` — birlashtirish |
| PATCH | `/api/students/{id}` | guruh admini | `{name?, ignored?}` |
| GET | `/api/users`, `/api/users/counts` | admin | Foydalanuvchilar |
| PATCH | `/api/users/{id}` | admin* | `status`; bosh admin uchun `roles`, `managed_by_id`, `hemis_id` |
| DELETE | `/api/users/{id}` | bosh admin | Soft-delete |
| GET | `/api/groups` | admin | Guruhlar (admin: faqat o'zinikilar) |
| POST | `/api/groups/{id}/toggle` | admin* | Faollikni almashtirish |
| POST/PUT/DELETE | `/api/groups` | bosh admin | CRUD |
| GET | `/api/reports/summary` | admin | Hisobot |
| * | `/api/zoom-accounts`, `/{id}/test`, `/{id}/users` | bosh admin | Zoom akkauntlar |
| * | `/api/lesson-slots` | bosh admin | Juftliklar |
| GET | `/api/audit-logs` | bosh admin | Amallar tarixi |
| POST | `/api/telegram/webhook` | Telegram | `X-Telegram-Bot-Api-Secret-Token` bilan |
| GET | `/l/{id}/start?signature=…` | imzolangan | Telegram'dagi "Darsni boshlash" |

`admin*` — Policy qo'shimcha tekshiradi (masalan, admin faqat o'ziga biriktirilgan xodim va guruhni o'zgartira oladi).

## Xavfsizlik

Eski versiyada topilgan muammolar va ularning yechimi:

1. **Maxfiy kalitlar kodda edi.** Endi hammasi `.env`da. Zoom `client_secret` va host havolasi (`start_url`, ichida ZAK) `APP_KEY` bilan shifrlanadi. API secret'ni hech qachon qaytarmaydi.
2. **Ruxsatlar tekshirilmagan edi.** Har bir amal `Policy` yoki `role:` middleware orqali tekshiriladi va testlar bilan qoplangan.
3. **Bloklash ishlamas edi.** `EnsureAccountStatus` middleware har so'rovda holatni tekshiradi va bloklanganning sessiyasini tugatadi.
4. **CSRF.** Barcha o'zgartirishlar POST/PATCH/DELETE orqali, Sanctum CSRF himoyasi bilan. HEMIS OAuth `state` parametri `hash_equals` bilan tekshiriladi.
5. **Brute-force.** Zaxira login: login+IP uchun 5 urinish/daqiqa. Login mavjudligi oshkor qilinmaydi. Dars yaratish: 10/daqiqa.
6. **Host havolasi.** Faqat dars egasiga beriladi, har safar Zoom'dan yangidan olinadi. Telegram'dagi havola imzolangan va dars tugashi bilan eskiradi.
7. **Ma'lumot yo'qolishi.** `cascadeOnDelete` olib tashlandi, foydalanuvchi va guruhlar soft-delete qilinadi.
8. **Poyga holati (race condition).** Dars yaratish Zoom host bo'yicha `Cache::lock` ostida bajariladi.
9. **XSS.** React avtomatik ekranlaydi, Telegram HTML xabarlarida `e()` ishlatiladi. nginx qat'iy CSP sarlavhasini qo'shadi.
10. **CSV injection.** `=`, `+`, `-`, `@` bilan boshlanadigan qiymatlar oldiga apostrof qo'yiladi.
11. **Audit.** Muhim amallar `audit_logs` jadvaliga yoziladi.
12. **Uchinchi tomon skriptlari.** CDN'lar yo'q: shriftlar va kutubxonalar build ichida.

## Tillar

Asosiy til **qoraqalpoqcha** (`kaa`, lotin yozuvi, 2016-yilgi alifbo), ikkinchisi **o'zbekcha** (`uz`). Kalit sifatida o'zbekcha matnning o'zi ishlatiladi, shuning uchun o'zbekcha uchun alohida lug'at yo'q.

| Qayerda | Fayl |
|---|---|
| Frontend matnlari | `frontend/src/i18n/kaa.ts` — `t('Dars yaratish')` → `"Sabaq jaratıw"` |
| Backend xabarlari, Telegram, CSV | `backend/lang/kaa.json` — `__('...')` |
| Tekshiruv (validation) xabarlari | `backend/lang/kaa/validation.php`, `backend/lang/uz/validation.php` |

Interfeys tili (`frontend/src/i18n/locale.ts`, `AuthProvider`):
1. Tizimga kirmagan foydalanuvchi — doim qoraqalpoqcha. Kirish sahifasida UZ tanlansa, u faqat shu brauzer oynasida (`sessionStorage`) saqlanadi va kirgandan keyin profilga yoziladi.
2. Kirgan foydalanuvchi — profilidagi til (`users.locale`). Tanlamagan bo'lsa (`null`) — qoraqalpoqcha.
3. Tizimdan chiqqanda yana qoraqalpoqcha.

Backend tili:
1. Frontend har so'rovda `X-Locale` sarlavhasini yuboradi.
2. Sarlavha bo'lmasa, foydalanuvchi profilidagi til (`users.locale`) olinadi.
3. Ikkalasi ham bo'lmasa, `.env`dagi `APP_LOCALE` (`kaa` bo'lishi kerak).

Telegram xabarlari har doim qabul qiluvchining profilidagi tilda yuboriladi. Amallar jurnali ham ko'rayotgan foydalanuvchining tilida ko'rsatiladi.

Atama o'zgartirilsa (masalan, "Xodim" → "Foydalanuvchi"), eski kalitni `kaa.json`da qoldiring va `backend/lang/uz.json`ga eski → yangi o'zbekcha matnni yozing: avval yozilgan amallar jurnali yozuvlari ham yangi atama bilan ko'rinadi.

Yangi matn qo'shganda uni `t('...')` yoki `__('...')` ichiga o'rang va tarjimasini `kaa.ts` yoki `kaa.json`ga yozing. Tarjimasi yo'q kalit o'zbekcha ko'rinadi, ishlab chiqish rejimida brauzer konsolida ogohlantirish chiqadi.

## Frontend dizayn tizimi

Ranglar universitet "MT" logotipidan olingan (`frontend/src/index.css`, `@theme`):

| Token | Qiymat | Ishlatilishi |
|---|---|---|
| `ink-900` | `#13284a` | Matn, yon menyu, "Efirda" paneli |
| `azure-500` | `#2f7bd0` | Asosiy tugmalar, tanlangan holat |
| `steel-*` | `#9aa6b4` atrofida | Ikkinchi darajali matn, chegaralar |
| `onair-500` | `#d9372b` | Faqat jonli dars belgisi |
| `mist` | `#eef2f7` | Sahifa foni |

Shriftlar: **Unbounded** (sarlavhalar, raqamlar) va **Onest** (asosiy matn). Ikkalasi ham lotin va kirill yozuvlarini qo'llaydi va `@fontsource` orqali build ichiga kiradi. Bezak sifatida qoraqalpoq naqshi ("qo'chqor shox") faqat kirish sahifasida bir marta ishlatilgan.
