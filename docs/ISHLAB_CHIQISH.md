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

Vite `/api`, `/auth`, `/sanctum`, `/l` so'rovlarini Laravel'ga proksi qiladi (`frontend/vite.config.ts`). Brauzer uchun hammasi bitta manzilda bo'ladi, xuddi production'dagidek.

`DemoSeeder` namunaviy xodimlar, guruhlar va 30 kunlik dars tarixini yaratadi. Kirish: **admin / demo-password-2026**. Demo Zoom kalitlari soxta, shuning uchun lokal muhitda haqiqiy dars yaratish uchun haqiqiy Zoom akkaunt qo'shing.

### Yo'lda ishlashni sinash (ixtiyoriy, masalan /zoom)

```bash
cd frontend
VITE_BASE_PATH=/zoom/ npm run dev     # http://localhost:5173/zoom/
```

Vite proksisi production'dagi nginx kabi `/zoom` ni olib tashlab, `X-Forwarded-Prefix: /zoom` bilan uzatadi. Frontendda manzillar `src/lib/base.ts` orqali quriladi: React Router `basename`, axios `baseURL` va `withBase()` (oddiy `<a href>` havolalar: eksport, HEMIS). Yangi to'g'ridan-to'g'ri havola qo'shsangiz, `withBase()` dan foydalaning.

Backend yo'lni ikki yo'l bilan tushunadi: nginx `SCRIPT_NAME=/zoom/index.php` beradi (Symfony base path) yoki proksi `X-Forwarded-Prefix` yuboradi (`TrustProxies`). Artisan buyruqlaridagi havolalar `APP_URL` dagi yo'ldan olinadi.

## Testlar

```bash
cd backend
php artisan test                  # CI: php vendor/bin/phpunit
```

Testlar SQLite xotirasida ishlaydi. Tashqi xizmatlarga so'rov ketmaydi: `Http::preventStrayRequests()` yoqilgan, Zoom, HEMIS va Telegram javoblari soxtalashtirilgan. CI'da `.env` yo'q — `.env` ga bog'liq test o'zining vaqtinchalik faylini yaratadi.

| Test | Nimani tekshiradi |
|---|---|
| `HemisLoginTest` | OAuth state, yangi xodim "kutish" holatida, bloklangan/o'chirilgan/talaba/boshqa OTM rad etilishi, faqat ruxsat etilgan maydonlar saqlanishi |
| `HemisStudentSyncTest` | HEMIS SYNC: faqat yangi/o'zgargan ID so'raladi, topilmadi/xato holatlari, token yo'qligi |
| `SessionAndStatusTest` | Zaxira login, rate limit, bloklangan sessiya, xavfsizlik sarlavhalari, parol almashtirish |
| `LessonTest` | Dars yaratish (faqat juftlikka, kun tanlash), guruh va host bandligi, host tanlash, Zoom xatolari, host havolasi faqat egasiga, imzolangan Telegram havolasi, XLSX eksport (formula injection) |
| `LessonSeriesTest` | Haftalik darslar: 1 soat oldin yaratish, bekor qilingan kun, host band bo'lsa boshqasi, xato xabari, ruxsatlar |
| `AttendanceTest` | Zoom holatini kuzatish, hisobotdan davomat, talabani tanish, `past_meetings` zaxirasi, qayta urinish, birlashtirish |
| `ZoomWebhookTest` | Imzo va vaqt tekshiruvi, CRC (`endpoint.url_validation`), jonli qatnashchilar, `recording.completed` |
| `RecordingTest` | Yozuvlarni ro'yxatga olish, yuklash va hajm tekshiruvi, Zoom'dan o'chirish, Range so'rovlari, X-Accel-Redirect, ruxsatlar |
| `SemesterReportTest` | Semestr hisoboti, o'zlashtirish taxmini mezonlari, XLSX |
| `AcademicYearTest` | O'quv yillari, bitta joriy yil, darslarni semestrga biriktirish, O'quv bo'limi faqat ko'radi |
| `AdministrationTest` | Rollar bo'yicha ruxsatlar, Zoom secret shifrlanishi, soft-delete, hisobotlar, amallar jurnali |
| `TelegramTest`, `LocaleTest` | Telegram webhook va ulash; til tanlash |
| `DeploymentTest` | O'z domenida login, `/zoom` yo'lida ishlash (SCRIPT_NAME va X-Forwarded-Prefix), `app:check` |
| `Unit/StudentNameTest`, `Unit/UserNameTest` | Ism kaliti (kirill/lotin, tartib, qurilma nomlari); "Familiya Ism" ko'rinishi |

Frontend tekshiruvi:

```bash
cd frontend
npm run typecheck
npm run build
```

Kod uslubi (backend): `vendor/bin/pint` (CI `pint --test` bilan tekshiradi).

## Tuzilma

```
backend/
  app/
    Actions/            CreateLesson, EndLesson, CreateSeries, MaterializeSeries, SyncLessonAttendance,
                        MergeStudents, SyncStudentsFromHemis, SyncZoomHosts
    Console/Commands/   lessons:sync-statuses, lessons:track-zoom, lessons:sync-attendance,
                        lessons:materialize-series, recordings:process, hemis:student,
                        app:create-admin, app:check, telegram:set-webhook, legacy:import
    Enums/              Role, UserStatus, LessonStatus, Assessment
    Http/Controllers/   Api/ (JSON API), Auth/ (HemisController, SessionController),
                        ZoomWebhookController, TelegramWebhookController
    Models/             User, Group, Student, StudentAlias, ZoomAccount, ZoomHost, LessonSlot, Lesson,
                        LessonSeries, LessonSeriesSkip, LessonAttendance, ZoomParticipation, LiveParticipant,
                        Recording, AcademicYear, Semester, Setting, AuditLog
    Policies/           LessonPolicy, LessonSeriesPolicy, GroupPolicy, UserPolicy
    Services/           Hemis/ (HemisOAuth, HemisStudentApi), Zoom/ (ZoomClient, WebhookHandler, LiveMonitor,
                        MeetingTracker, RecordingSync), Telegram/TelegramBot
    Support/            HostAvailability, SemesterReport, AttendanceCalculator, StudentResolver, StudentName,
                        RecordingSettings, Xlsx, Audit, Notifier
  config/recordings.php video yozuvlar papkasi, minimal bo'sh joy, X-Accel prefiksi
  routes/               api.php, web.php (HEMIS OAuth, /l/{id}/start), console.php (scheduler)

frontend/src/
  auth/                 AuthProvider (sessiya, rollar, ruxsatlar)
  components/ui/        Button, Field, Modal, Badge, Segmented, StaffName, ...
  features/             lessons, calendar, reports, recordings
  pages/                sahifalar
  lib/                  api (axios + CSRF), base (yo'l prefiksi), format (sana/vaqt, Toshkent), types
  i18n/                 kaa.ts lug'at, locale
```

## Ma'lumotlar modeli

| Jadval | Tavsif |
|---|---|
| `users` | `roles` (JSON: teacher/education/super_admin), `status` (pending/active/blocked), `hemis_id`, familiya/ism/otasining ismi, `picture_url`, `can_manage_calendar`, `telegram_chat_id`, `locale`. Telefon, email saqlanmaydi. Soft-delete |
| `zoom_accounts` | S2S OAuth kalitlari va `webhook_secret` (shifrlangan), `webhook_received_at` |
| `zoom_hosts` | Akkaunt foydalanuvchilari: email, turi (Basic/Licensed), faollik. Dars yaratishda bandligi `HostAvailability` bilan tekshiriladi |
| `groups` | Guruh → Zoom akkaunt + standart host, kurs, faollik. Soft-delete |
| `lesson_slots` | Juftliklar: boshlanish vaqti, davomiylik |
| `lessons` | O'qituvchi, guruh, host, juftlik, semestr (`semester_id` — sanadan avtomatik), Zoom meeting ID, havolalar (`start_url` shifrlangan), `record_cloud`, holat. `zoom_*` — Zoom'dagi haqiqiy holat, `attendance_*` — hisobot olish holati |
| `lesson_series`, `lesson_series_skips` | Haftalik darslar: hafta kuni, juftlik, guruh, host, sanalar; bekor qilingan kunlar. `lessons:materialize-series` 60 daqiqa oldin dars yaratadi |
| `academic_years`, `semesters` | O'quv yili (planned/active/archived) va uning Kuzgi/Bahorgi semestrlari |
| `students` | Guruh talabalari: ism, `hemis_id`, HEMIS'dan olingan F.I.Sh. va rasm, sinxronlash holati, `is_ignored` |
| `student_aliases` | Tanish kalitlari: `name` (`StudentName::key()`), `zoom_id`, email (faqat `sha256:` xeshi) |
| `zoom_participations` | Hisobotdagi har bir kirish-chiqish (xom ma'lumot; birlashtirishda davomat qayta hisoblanadi) |
| `lesson_attendances` | Dars bo'yicha talabaning yakuniy qatnashuvi: jami soniya, kechikish, kirishlar soni |
| `live_participants` | Webhook'dan kelgan jonli kirish-chiqishlar (Monitoring). 7 kundan keyin tozalanadi |
| `recordings` | Zoom yozuv fayllari: turi, hajmi, holat (pending/downloaded/skipped/failed), urinishlar soni va xato matni, server yo'li, Zoom'dan o'chirilgan vaqti |
| `settings` | Kalit-qiymat sozlamalar (o'zlashtirish mezoni, video yozuvlar), keshlanadi |
| `audit_logs` | Amallar jurnali (2 yildan keyin tozalanadi) |

### Zoom bilan ishlash

- **Kuzatuv**: webhook'lar (`meeting.started/ended/participant_joined/participant_left`) holat va jonli qatnashchilarni darhol yangilaydi (`WebhookHandler`). Zaxira sifatida `lessons:track-zoom` har daqiqa `GET /meetings/{id}` ni so'raydi (`started` → `waiting` o'tishi host tugatganini bildiradi), webhook bo'lmasa jonli ro'yxat Dashboard API'dan (`/metrics/meetings/{id}/participants?type=live`) olinadi.
- **Davomat**: tugagan dars uchun `SyncLessonAttendance` — `/past_meetings/{id}/instances`, `/past_meetings/{uuid}` va `/report/meetings/{uuid}/participants` (ruxsat bo'lmasa `/past_meetings/{uuid}/participants`). Zoom bu ma'lumotni taxminan 6 oy saqlaydi.
- **Webhook xavfsizligi**: `x-zm-signature` = `v0=` + HMAC-SHA256(secret, `v0:{timestamp}:{xom body}`), 5 daqiqalik oyna. CRC javobi ham faqat imzo to'g'ri bo'lsa beriladi (aks holda endpoint HMAC "oracle"iga aylanadi).
- **Video**: `recording.completed` → `RecordingSync::register`; `recordings:process` fayllarni Bearer token bilan yuklaydi, hajmini Zoom'dagi bilan solishtiradi, keyin `DELETE /meetings/{uuid}/recordings?action=trash|delete`. UUID `/` bilan boshlansa yoki `//` bo'lsa, ikki marta kodlanadi.

Dars holati bazada saqlanadi va har daqiqada `lessons:sync-statuses` yangilaydi. API holatni vaqtdan qo'shimcha hisoblaydi (`Lesson::effectiveStatus()`), shuning uchun scheduler to'xtab qolsa ham foydalanuvchi to'g'ri holatni ko'radi.

## API

Barcha `/api/*` marshrutlari JSON qaytaradi. Autentifikatsiya: Sanctum SPA (cookie sessiya + `X-XSRF-TOKEN`). O'zgartiruvchi so'rovlardan oldin frontend `GET /sanctum/csrf-cookie` chaqiradi. "Faol" — tasdiqlangan foydalanuvchi; har bir yozuv `Policy` bilan qo'shimcha tekshiriladi (o'qituvchi faqat o'z darslarini ko'radi).

| Metod | Manzil | Kim | Tavsif |
|---|---|---|---|
| GET | `/auth/hemis/redirect`, `/auth/hemis/callback` | mehmon | HEMIS OAuth |
| GET | `/api/config` | hamma | Ochiq sozlamalar |
| POST | `/api/auth/login`, `/api/auth/logout` | — | Zaxira login (faqat Administrator), chiqish |
| GET/PATCH | `/api/me` | kirgan | Joriy foydalanuvchi, ruxsatlar; ism (Administrator), til |
| PUT | `/api/me/password` | parolli | Parol almashtirish |
| POST/DELETE | `/api/me/telegram` | kirgan | Telegram ulash havolasi / uzish |
| GET | `/api/dashboard`, `/api/calendar?from&to` | faol | Bosh sahifa; hafta kalendari |
| GET | `/api/lessons` | faol | Filtr: status, group_id, mine, from, to, semester_id, academic_year_id |
| GET | `/api/lessons/options` | faol | Kun bo'yicha juftliklar, guruhlar va hostlar bandligi |
| POST | `/api/lessons` | O'qituvchi, Administrator | `{group_id, slot_id, date?, zoom_host_id?, topic?, repeat_weekly?}` |
| POST | `/api/lessons/{id}/start`, `/end` | egasi / Administrator | Host havolasi; yakunlash yoki bekor qilish |
| GET | `/api/lessons/export` | O'quv bo'limi, Administrator | XLSX |
| GET | `/api/lessons/{id}/attendance`, `/attendance/export` | dars ko'ruvchi | Qatnashuv; XLSX |
| POST | `/api/lessons/{id}/attendance/sync` | dars ko'ruvchi | Zoom hisobotini qayta olish |
| GET | `/api/lessons/{id}/live` | dars ko'ruvchi | Jonli qatnashchilar |
| GET | `/api/lessons/{id}/recordings`, `/api/recordings`, `/api/recordings/{id}/file` | dars ko'ruvchi | Video yozuvlar; fayl (Range, `?download=1`) |
| * | `/api/series`, `/{id}/cancel`, `/{id}/skips` | O'qituvchi (kalendar huquqi bilan), Administrator | Haftalik darslar |
| GET | `/api/semesters` | faol | Semestrlar (filtrlar uchun) |
| GET | `/api/monitor` | O'quv bo'limi, Administrator | Bugungi va efirdagi darslar |
| GET/PATCH/DELETE | `/api/users`, `/api/users/counts` | O'quv bo'limi (ko'radi va tasdiqlaydi: `pending` → `active`), Administrator | Foydalanuvchilar, rollar, `can_manage_calendar`, ism |
| * | `/api/groups`, `/{id}/toggle` | O'quv bo'limi (ko'radi), Administrator | Guruhlar |
| GET/POST/PATCH | `/api/groups/{id}/students`, `/merge`, `/hemis-sync`, `/api/students/{id}` | O'quv bo'limi, Administrator | Talabalar, birlashtirish, HEMIS SYNC, `{name?, ignored?, hemis_id?}` |
| GET | `/api/reports/summary`, `/api/reports/semester`, `/semester/export` | O'quv bo'limi, Administrator | Hisobotlar; XLSX |
| GET | `/api/academic-years` | O'quv bo'limi, Administrator | O'quv yillari |
| POST/PUT/PATCH/DELETE | `/api/academic-years`, `/{id}/status` | Administrator | O'quv yillarini boshqarish |
| * | `/api/zoom-accounts`, `/{id}/test`, `/{id}/users`, `/{id}/sync-hosts`, `/api/zoom-hosts` | Administrator | Zoom akkauntlar va hostlar |
| * | `/api/lesson-slots`, `/api/settings`, `/api/audit-logs`, `/api/recordings/{id}/retry` | Administrator | Juftliklar, sozlamalar, jurnal |
| POST | `/api/zoom/webhook/{account}` | Zoom | `x-zm-signature` bilan |
| POST | `/api/telegram/webhook` | Telegram | `X-Telegram-Bot-Api-Secret-Token` bilan |
| GET | `/l/{id}/start?signature=…` | imzolangan | Telegram'dagi "Darsni boshlash" |

## Xavfsizlik

Eski versiyada topilgan muammolar va ularning yechimi:

1. **Maxfiy kalitlar kodda edi.** Endi hammasi `.env`da. Zoom `client_secret`, webhook secret va host havolasi (`start_url`, ichida ZAK) `APP_KEY` bilan shifrlanadi. API ularni hech qachon qaytarmaydi.
2. **Ruxsatlar tekshirilmagan edi.** Har bir amal `Policy` yoki `role:` middleware orqali tekshiriladi va testlar bilan qoplangan.
3. **Bloklash ishlamas edi.** `EnsureAccountStatus` middleware har so'rovda holatni tekshiradi va bloklanganning sessiyasini tugatadi.
4. **CSRF.** Barcha o'zgartirishlar POST/PATCH/DELETE orqali, Sanctum CSRF himoyasi bilan. HEMIS OAuth `state` parametri `hash_equals` bilan tekshiriladi.
5. **Brute-force.** Zaxira login: login+IP uchun 5 urinish/daqiqa. Dars yaratish: 10/daqiqa. HEMIS SYNC: 10/daqiqa. Webhook'lar alohida limit bilan.
6. **Host havolasi.** Faqat dars egasiga beriladi, har safar Zoom'dan yangidan olinadi. Telegram'dagi havola imzolangan va dars tugashi bilan eskiradi.
7. **Ma'lumot yo'qolishi.** `cascadeOnDelete` olib tashlandi, foydalanuvchi va guruhlar soft-delete qilinadi. Video Zoom'dan faqat hajmi tekshirilgandan keyin o'chiriladi.
8. **Poyga holati.** Dars yaratish avval guruh, keyin host bo'yicha `Cache::lock` ostida bajariladi (tartib doim bir xil — o'zaro kutib qolish yo'q).
9. **XSS.** React avtomatik ekranlaydi, Telegram HTML xabarlarida `e()`. nginx qat'iy CSP sarlavhasini qo'shadi.
10. **Formula injection.** XLSX'da barcha matnlar `StringCell` sifatida yoziladi — `=` bilan boshlangan ism formula bo'lib qolmaydi.
11. **Shaxsiy ma'lumotlar.** Xodim va talabadan faqat F.I.Sh., HEMIS ID va rasm havolasi saqlanadi. Zoom'dagi email faqat xesh ko'rinishida (tanish uchun).
12. **Webhook.** Zoom imzosi va vaqti tekshiriladi; imzosiz CRC so'roviga javob berilmaydi.
13. **Video fayllar.** Har bir so'rovda dars ko'rish ruxsati tekshiriladi; nginx'ning ichki (`internal`) manzili tashqaridan ochilmaydi.
14. **Audit.** Muhim amallar `audit_logs` jadvaliga yoziladi.
15. **Uchinchi tomon skriptlari.** CDN'lar yo'q: shriftlar va kutubxonalar build ichida.

## Tillar

Asosiy til **qoraqalpoqcha** (`kaa`, lotin yozuvi, 2016-yilgi alifbo), ikkinchisi **o'zbekcha** (`uz`). Kalit sifatida o'zbekcha matnning o'zi ishlatiladi, shuning uchun o'zbekcha uchun alohida lug'at yo'q.

| Qayerda | Fayl |
|---|---|
| Frontend matnlari | `frontend/src/i18n/kaa.ts` — `t('Dars yaratish')` → `"Sabaq jaratıw"` |
| Backend xabarlari, Telegram, Excel | `backend/lang/kaa.json` — `__('...')` |
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
