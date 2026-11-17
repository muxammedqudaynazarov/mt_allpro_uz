/**
 * Joriy til. React'dan tashqarida ham kerak bo'ladi (sana formatlash, API sarlavhasi),
 * shuning uchun alohida modulda saqlanadi.
 *
 * Qaysi til ko'rsatiladi:
 *  - tizimga kirmagan foydalanuvchi — doim asosiy til (qoraqalpoqcha). Kirish sahifasida
 *    boshqa til tanlansa, u faqat shu brauzer oynasida (HEMIS'ga borib-kelguncha) saqlanadi;
 *  - tizimga kirgan foydalanuvchi — profilida tanlangan til (users.locale).
 */
export type Locale = 'kaa' | 'uz'

export const LOCALES: { value: Locale; label: string; short: string; htmlLang: string }[] = [
  { value: 'kaa', label: 'Qaraqalpaqsha', short: 'QQ', htmlLang: 'kaa' },
  { value: 'uz', label: 'Oʻzbekcha', short: 'UZ', htmlLang: 'uz' },
]

export const DEFAULT_LOCALE: Locale = 'kaa'

/** Kirishdan oldin tanlangan til — sessionStorage, oyna yopilganda o'chadi. */
const GUEST_KEY = 'mt.guest-locale'
/** Oxirgi kirgan foydalanuvchining tili — sahifa yangilanganda til "sakramasligi" uchun. */
const USER_KEY = 'mt.user-locale'
/** Oldingi versiya tanlovni shu kalitda doimiy saqlardi; endi ishlatilmaydi. */
const LEGACY_KEY = 'mt.locale'

export function isLocale(value: unknown): value is Locale {
  return value === 'kaa' || value === 'uz'
}

function read(storage: () => Storage, key: string): Locale | null {
  try {
    const value = storage().getItem(key)
    return isLocale(value) ? value : null
  } catch {
    return null
  }
}

function write(storage: () => Storage, key: string, value: Locale | null) {
  try {
    if (value) storage().setItem(key, value)
    else storage().removeItem(key)
  } catch {
    // Brauzer xotirasi yopiq bo'lsa, til faqat joriy sahifada amal qiladi.
  }
}

const session = () => window.sessionStorage
const local = () => window.localStorage

write(local, LEGACY_KEY, null)

/** Mehmon shu oynada tanlagan til. */
export function guestLocale(): Locale | null {
  return read(session, GUEST_KEY)
}

/** Mehmon tanlovini olib, o'chiradi: kirgandan keyin u profilga yoziladi. */
export function takeGuestLocale(): Locale | null {
  const value = guestLocale()
  write(session, GUEST_KEY, null)
  return value
}

export function rememberUserLocale(locale: Locale | null) {
  write(local, USER_KEY, locale)
}

let current: Locale = guestLocale() ?? read(local, USER_KEY) ?? DEFAULT_LOCALE

export function getLocale(): Locale {
  return current
}

/** Tilni qo'llaydi. `forGuest` — mehmon tanlovi sifatida shu oynada eslab qolinadi. */
export function applyLocale(locale: Locale, forGuest = false) {
  current = locale
  document.documentElement.lang = LOCALES.find((item) => item.value === locale)?.htmlLang ?? locale
  if (forGuest) write(session, GUEST_KEY, locale)
}
