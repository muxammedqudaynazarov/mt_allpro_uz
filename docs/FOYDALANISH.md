# Foydalanish qo'llanmasi

Tizimda uch xil rol bor. Bir xodimda bir nechta rol bo'lishi mumkin: masalan, administrator o'zi ham dars o'tadi.

| Rol | Nima qila oladi |
|---|---|
| **O'qituvchi** | Dars yaratadi, boshlaydi, yakunlaydi. Faqat o'z darslarini ko'radi |
| **Administrator** | O'qituvchi imkoniyatlari, yangi xodimlarni tasdiqlash yoki bloklash, o'ziga biriktirilgan guruhlarni yoqish/o'chirish, guruhlari bo'yicha hisobot |
| **Bosh administrator** | Hammasi: Zoom akkauntlar, guruhlar, dars vaqtlari, rollar, amallar tarixi |

---

## O'qituvchi uchun

### Tizimga kirish

1. **mt.allpro.uz** saytini oching va **"HEMIS orqali kirish"** tugmasini bosing.
2. HEMIS sahifasida odatdagi login va parolingizni kiriting.
3. Birinchi marta kirsangiz, hisobingiz **"Tasdiq kutmoqda"** holatida bo'ladi. Masʼul administrator tasdiqlagach, dars yaratishingiz mumkin. Tasdiqlanganda Telegram'ga xabar keladi (Telegram ulangan bo'lsa).

### Telegram'ni ulash (bir marta)

1. Chap menyuning pastidagi ismingizni bosing, **Profil** sahifasi ochiladi.
2. **"Telegram'ni ulash"** tugmasini bosing.
3. Telefonda QR kodni skanerlang yoki kompyuterda **"Telegram'da ochish"** tugmasini bosing.
4. Botda **Start** tugmasini bosing. Sahifada "Telegram ulandi" xabari chiqadi.

Endi har bir yaratilgan dars uchun Telegram'ga **"Darsni boshlash"** va **"Guruhga yuborish"** tugmalari keladi.

### Dars yaratish

1. **"Dars yaratish"** tugmasini bosing.
2. **Qachon?** — **"Hozir"** (darhol, 80 daqiqa) yoki bugungi paralardan birini tanlang. Tugagan paralar tanlanmaydi. Boshlangan para tanlansa, dars hozirdan para oxirigacha davom etadi.
3. **Qaysi guruh?** — kursni, keyin guruhni tanlang. Shu vaqtda boshqa darsi bor guruhlar **"Band"** deb ko'rsatiladi va tanlanmaydi.
4. **Mavzu** — ixtiyoriy.
5. **"Darsni yaratish"** tugmasini bosing.

Dars yaratilgach, oynada talabalar havolasi va kirish kodi chiqadi:

- **Darsni boshlash**: Zoom'ni host (boshlovchi) sifatida ochadi.
- **Nusxalash**: havolani nusxalaydi.
- **Guruhga Telegram orqali yuborish**: Telegram'ni ochadi, guruh chatini tanlab havolani yuborasiz.

### Darsni boshlash va yakunlash

Bosh sahifadagi **"Efirda"** paneli hozir davom etayotgan darslarni ko'rsatadi: qancha vaqt qolgani, Zoom'ni ochish va havolani yuborish tugmalari shu yerda.

**Yakunlash** tugmasi Zoom uchrashuvini barcha ishtirokchilar uchun yopadi. Hali boshlanmagan dars yakunlansa, u **"Bekor qilingan"** bo'ladi. Vaqti tugagan dars avtomatik **"Yakunlangan"** holatiga o'tadi.

> Host havolasi faqat sizga beriladi va har safar yangidan olinadi. Uni boshqalarga yubormang. Talabalarga faqat **talabalar havolasini** yuboring.

### Darslar tarixi

**Darslar** sahifasida barcha darslaringiz bor. Holat (Efirda, Rejalashtirilgan, Yakunlangan) va sana bo'yicha filtrlash mumkin.

---

## Administrator uchun

### Yangi xodimlarni tasdiqlash

Yangi xodim HEMIS orqali kirganda bosh sahifada sariq xabar chiqadi, menyudagi **"Xodimlar"** yonida esa son ko'rinadi. Telegram ulangan bo'lsa, botdan ham xabar keladi.

1. **Xodimlar → Tasdiq kutmoqda**.
2. Xodimni aniqlang (ism, HEMIS ID, bo'lim) va **"Tasdiqlash"**ni bosing.

Siz tasdiqlagan xodim avtomatik sizga biriktiriladi. Keyinchalik uni faqat siz (yoki bosh administrator) bloklay olasiz.

### Bloklash

**Xodimlar** jadvalida qator oxiridagi **⋯** tugmasi → **Bloklash**. Bloklangan xodimning ochiq sessiyasi darhol tugaydi va u qayta kira olmaydi. **Faollashtirish** bilan qaytariladi.

### Guruhlar

**Guruhlar** sahifasida sizga biriktirilgan guruhlar ko'rinadi. **"Faol"** kalitini o'chirsangiz, o'qituvchilar bu guruhga dars yarata olmaydi (masalan, ta'til yoki amaliyot paytida).

### Hisobotlar

**Hisobotlar** sahifasi tanlangan davr bo'yicha natijalarni ko'rsatadi: darslar soni, umumiy vaqt, o'qituvchilar va guruhlar kesimida. **"Batafsil ro'yxat (CSV)"** fayli Excel'da ochiladi. **Darslar** sahifasidagi filtrlangan ro'yxatni ham CSV qilib yuklab olish mumkin.

---

## Bosh administrator uchun

### Birinchi sozlash tartibi

1. **Zoom akkauntlar**: Server-to-Server OAuth ma'lumotlarini kiriting va "Ulanishni tekshirish"ni bosing ([O'rnatish, 11-bo'lim](ORNATISH.md#11-zoom-ulash)).
2. **Dars vaqtlari**: paralarni tekshiring yoki o'zgartiring (standart: 13:00, 14:30, 16:00, 17:30, 19:00; 80 daqiqa).
3. **Guruhlar → Guruh qo'shish**: nomi, kursi, Zoom akkaunt, host email va masʼul administrator.
4. **Xodimlar**: administratorlarga rol bering (⋯ → **Rol va sozlamalar**).

### Rollar va masʼul administrator

**⋯ → Rol va sozlamalar** oynasida:

- **Rollar**: o'qituvchi / administrator / bosh administrator. O'z rolingizni o'zgartira olmaysiz: bu tasodifan tizimdan chiqib qolishdan himoya qiladi.
- **HEMIS ID**: eski tizimdan ko'chirilgan xodimni uning HEMIS hisobiga bog'lash uchun.
- **Masʼul administrator**: xodim qaysi administratorga tegishli.

### Xodimni o'chirish

**⋯ → O'chirish**. Xodim tizimga kira olmaydi, lekin uning darslari tarixi hisobotlarda saqlanadi.

### Amallar tarixi

**Amallar tarixi** sahifasida kim, qachon, qaysi amalni bajargani yoziladi: kirishlar, dars yaratish va yakunlash, rol o'zgartirish, guruh va Zoom sozlamalari. Yozuvlar 2 yil saqlanadi.

### HEMIS ishlamay qolsa

Kirish sahifasining pastida **"Administrator login va paroli bilan kirish"** havolasi bor. Zaxira login serverda `php artisan app:create-admin` buyrug'i bilan yaratiladi ([O'rnatish, 9-bo'lim](ORNATISH.md#9-bosh-administrator)). O'qituvchilar uchun zaxira login yo'q.

---

## Ko'p beriladigan savollar

**Guruh "Band" ko'rinyapti, lekin u yerda dars yo'q.**
Shu guruh yoki shu Zoom hostga biriktirilgan boshqa guruhda tanlangan vaqt bilan kesishadigan dars bor. **Darslar → Efirda / Rejalashtirilgan**da tekshiring. Kerak bo'lsa, o'qituvchi yoki administrator darsni **Yakunlash** orqali bo'shatadi.

**"Darsni boshlash" bosilganda Zoom ochilmadi.**
Brauzer yangi oynani bloklagan bo'lishi mumkin: manzil satridagi "popup" belgisini bosib ruxsat bering.

**Telegram'dagi "Darsni boshlash" tugmasi ishlamayapti.**
Tugma dars tugashi bilan eskiradi. Dars davom etayotgan bo'lsa, saytdagi tugmadan foydalaning.

**Ism-familiyam noto'g'ri yozilgan.**
Ma'lumotlar HEMIS'dan olinadi va har kirishda yangilanadi. HEMIS'dagi ma'lumotni to'g'rilating.
