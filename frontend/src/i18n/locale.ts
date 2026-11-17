/**
 * Joriy til. React'dan tashqarida ham kerak bo'ladi (sana formatlash, API sarlavhasi),
 * shuning uchun alohida modulda saqlanadi.
 */
export type Locale = 'kaa' | 'uz'

export const LOCALES: { value: Locale; label: string; short: string; htmlLang: string }[] = [
  { value: 'kaa', label: 'Qaraqalpaqsha', short: 'QQ', htmlLang: 'kaa' },
  { value: 'uz', label: 'Oʻzbekcha', short: 'UZ', htmlLang: 'uz' },
]

export const DEFAULT_LOCALE: Locale = 'kaa'
const STORAGE_KEY = 'mt.locale'

function isLocale(value: unknown): value is Locale {
  return value === 'kaa' || value === 'uz'
}

/** Foydalanuvchi shu qurilmada tilni o'zi tanlaganmi. */
export function storedLocale(): Locale | null {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY)
    return isLocale(value) ? value : null
  } catch {
    return null
  }
}

let current: Locale = storedLocale() ?? DEFAULT_LOCALE

export function getLocale(): Locale {
  return current
}

export function applyLocale(locale: Locale, remember: boolean) {
  current = locale
  document.documentElement.lang = LOCALES.find((item) => item.value === locale)?.htmlLang ?? locale
  if (remember) {
    try {
      window.localStorage.setItem(STORAGE_KEY, locale)
    } catch {
      // Brauzer xotirasi yopiq bo'lsa, til faqat shu sessiyada saqlanadi.
    }
  }
}

export { isLocale }
