import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { kaa } from './kaa'
import { applyLocale, getLocale, type Locale } from './locale'

/**
 * Yengil tarjima tizimi. Kalit sifatida o'zbekcha matnning o'zi ishlatiladi:
 * `t('Dars yaratish')` o'zbekchada o'zini, qoraqalpoqchada `kaa.ts` dagi tarjimani qaytaradi.
 * Shu sababli o'zbekcha uchun alohida lug'at kerak emas.
 *
 * Matn ichidagi o'zgaruvchilar: t('{count} ta dars', { count: 5 })
 */
export type Vars = Record<string, string | number>
export type TFunction = (key: string, vars?: Vars) => string

const dictionaries: Record<Locale, Record<string, string>> = { kaa, uz: {} }

export function translate(locale: Locale, key: string, vars?: Vars): string {
  let text = dictionaries[locale][key]
  if (text === undefined) {
    if (import.meta.env.DEV && locale !== 'uz') console.warn(`[i18n] tarjima yoʻq (${locale}):`, key)
    text = key
  }
  if (vars) {
    text = text.replace(/\{(\w+)\}/g, (match, name: string) => (name in vars ? String(vars[name]) : match))
  }
  return text
}

/** React'dan tashqarida (masalan, axios xatolarida) tarjima qilish uchun. */
export function tGlobal(key: string, vars?: Vars): string {
  return translate(getLocale(), key, vars)
}

interface I18nContextValue {
  locale: Locale
  /** `guest: true` — mehmon tanlovi, shu brauzer oynasida eslab qolinadi. */
  setLocale: (locale: Locale, options?: { guest?: boolean }) => void
  t: TFunction
}

const I18nContext = createContext<I18nContextValue | null>(null)

export function I18nProvider({ children, onChange }: { children: ReactNode; onChange?: (locale: Locale) => void }) {
  const [locale, setLocaleState] = useState<Locale>(getLocale)

  useEffect(() => {
    applyLocale(locale)
    document.title = translate(locale, 'Masofaviy taʼlim tizimi')
  }, [locale])

  const setLocale = useCallback(
    (next: Locale, options?: { guest?: boolean }) => {
      applyLocale(next, options?.guest ?? false)
      setLocaleState(next)
      onChange?.(next)
    },
    [onChange],
  )

  const t = useCallback<TFunction>((key, vars) => translate(locale, key, vars), [locale])
  const value = useMemo(() => ({ locale, setLocale, t }), [locale, setLocale, t])

  // Til almashganda butun ilova qayta chiziladi — sana va boshqa formatlar ham yangilanadi.
  return (
    <I18nContext.Provider value={value}>
      <div key={locale} className="contents">
        {children}
      </div>
    </I18nContext.Provider>
  )
}

export function useI18n(): I18nContextValue {
  const context = useContext(I18nContext)
  if (!context) throw new Error('useI18n I18nProvider ichida ishlatilishi kerak')
  return context
}

export function useT(): TFunction {
  return useI18n().t
}
