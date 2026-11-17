import { Link, useNavigate } from 'react-router'
import { Ban, Hourglass, Send } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { Button, buttonClass } from '@/components/ui/Button'
import { LanguageSwitch } from '@/components/ui/LanguageSwitch'
import { Logo } from '@/components/ui/Logo'
import { useT } from '@/i18n/I18nProvider'

export default function PendingPage() {
  const t = useT()
  const { user, logout, refresh } = useAuth()
  const navigate = useNavigate()
  if (!user) return null
  const blocked = user.status === 'blocked'

  return (
    <div className="relative flex min-h-dvh items-center justify-center px-5 py-12">
      <LanguageSwitch className="absolute top-5 right-5" />
      <div className="w-full max-w-md text-center">
        <Logo className="mx-auto size-12" />
        <div className="mx-auto mt-8 flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
          {blocked ? <Ban className="size-6" /> : <Hourglass className="size-6" />}
        </div>
        <h1 className="mt-5 text-2xl font-semibold tracking-tight">
          {blocked ? t('Hisobingiz bloklangan') : t('Hisobingiz tasdiqlanishi kutilmoqda')}
        </h1>
        <p className="mt-3 text-[0.9375rem] leading-relaxed text-steel-600">
          {blocked
            ? t('Tizimdan foydalanish uchun masʼul administratorga murojaat qiling.')
            : t('{name}, siz HEMIS orqali muvaffaqiyatli kirdingiz. Administrator hisobingizni faollashtirgach, dars yarata olasiz.', { name: user.short_name })}
        </p>

        {!blocked && !user.telegram.linked && (
          <div className="mt-8 rounded-panel bg-white p-5 text-left ring-1 ring-steel-200">
            <p className="font-medium">{t('Kutayotganda Telegram’ni ulang')}</p>
            <p className="mt-1 text-sm leading-relaxed text-steel-600">{t('Dars havolalari va tasdiqlash haqidagi xabar Telegram orqali keladi.')}</p>
            <Link to="/profile" className={buttonClass({ variant: 'secondary', className: 'mt-4' })}>
              <Send className="size-4" />
              {t('Telegram’ni ulash')}
            </Link>
          </div>
        )}

        <div className="mt-8 flex justify-center gap-3">
          {!blocked && (
            <Button variant="secondary" onClick={() => refresh()}>
              {t('Holatni yangilash')}
            </Button>
          )}
          <Button
            variant="ghost"
            onClick={async () => {
              await logout()
              navigate('/login', { replace: true })
            }}
          >
            {t('Chiqish')}
          </Button>
        </div>
      </div>
    </div>
  )
}
