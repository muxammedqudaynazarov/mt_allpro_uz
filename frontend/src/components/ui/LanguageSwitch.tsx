import { useAuth } from '@/auth/AuthProvider'
import { useI18n } from '@/i18n/I18nProvider'
import { LOCALES, type Locale } from '@/i18n/locale'
import { api } from '@/lib/api'
import { cn } from './cn'

/** Til tanlash: QQ (qoraqalpoqcha) / UZ (o'zbekcha). Tanlov qurilmada va profilda saqlanadi. */
export function LanguageSwitch({ tone = 'light', className }: { tone?: 'light' | 'dark'; className?: string }) {
  const { locale, setLocale, t } = useI18n()
  const { user } = useAuth()

  function change(next: Locale) {
    if (next === locale) return
    setLocale(next)
    if (user) {
      // Telegram xabarlari ham shu tilda kelishi uchun profilga yozamiz.
      api.patch('/api/me', { locale: next }).catch(() => undefined)
    }
  }

  return (
    <div
      role="radiogroup"
      aria-label={t('Til')}
      className={cn('inline-flex rounded-full p-0.5 text-xs font-semibold', tone === 'dark' ? 'bg-white/10' : 'bg-steel-100', className)}
    >
      {LOCALES.map((item) => {
        const active = item.value === locale
        return (
          <button
            key={item.value}
            type="button"
            role="radio"
            aria-checked={active}
            title={item.label}
            onClick={() => change(item.value)}
            className={cn(
              'h-7 min-w-10 rounded-full px-2.5 transition-colors',
              tone === 'dark'
                ? active
                  ? 'bg-white text-ink-900'
                  : 'text-ink-200 hover:text-white'
                : active
                  ? 'bg-white text-ink-900 shadow-[0_1px_2px_rgb(19_40_74/0.12)]'
                  : 'text-steel-600 hover:text-ink-900',
            )}
          >
            {item.short}
          </button>
        )
      })}
    </div>
  )
}
