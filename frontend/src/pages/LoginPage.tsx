import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { AlertCircle, KeyRound, LogIn } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Field'
import { LanguageSwitch } from '@/components/ui/LanguageSwitch'
import { Logo } from '@/components/ui/Logo'
import { Ornament } from '@/components/ui/Ornament'
import { useT } from '@/i18n/I18nProvider'

// Matnlar o'zbekcha manba — ko'rsatishda t(...) orqali tarjima qilinadi.
const CALLBACK_ERRORS: Record<string, string> = {
  hemis_denied: 'HEMIS orqali kirish bekor qilindi.',
  hemis_failed: 'HEMIS bilan bogʻlanib boʻlmadi. Birozdan soʻng qayta urinib koʻring yoki administratorga murojaat qiling.',
  state: 'Kirish sessiyasi eskirgan. Qaytadan “HEMIS orqali kirish” tugmasini bosing.',
  blocked: 'Hisobingiz bloklangan. Administratorga murojaat qiling.',
  not_employee: 'Tizimga faqat universitet xodimlari kira oladi.',
  university: 'Bu HEMIS hisobi boshqa oliygohga tegishli.',
  registration_closed: 'Hisobingiz tizimda topilmadi. Administrator sizni qoʻshishi kerak.',
}

const STEPS = [
  { title: 'HEMIS orqali kiring', text: 'Alohida login va parol kerak emas.' },
  { title: 'Guruh va juftlikni tanlang', text: 'Zoom darsi bir zumda yaratiladi.' },
  { title: 'Havolani guruhga yuboring', text: 'Havola Telegram’ga ham keladi.' },
]

function AdminLoginForm() {
  const t = useT()
  const { refresh } = useAuth()
  const navigate = useNavigate()
  const [login, setLogin] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState('')
  const [loading, setLoading] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setLoading(true)
    setErrors({})
    setFormError('')
    try {
      await api.post('/api/auth/login', { login, password, remember: true })
      await refresh()
      navigate('/', { replace: true })
    } catch (error) {
      const fields = fieldErrors(error)
      if (Object.keys(fields).length) setErrors(fields)
      else setFormError(errorMessage(error))
    } finally {
      setLoading(false)
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <Input label={t('Login')} name="login" autoComplete="username" autoFocus value={login} onChange={(e) => setLogin(e.target.value)} error={errors.login} required />
      <Input
        label={t('Parol')}
        name="password"
        type="password"
        autoComplete="current-password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        error={errors.password}
        required
      />
      {formError && <p className="text-sm text-onair-600">{formError}</p>}
      <Button type="submit" variant="secondary" className="w-full" loading={loading} icon={<KeyRound className="size-4" />}>
        {t('Kirish')}
      </Button>
    </form>
  )
}

export default function LoginPage() {
  const t = useT()
  const { config } = useAuth()
  const [params] = useSearchParams()
  const [adminMode, setAdminMode] = useState(false)
  const error = params.get('error')
  const hemisEnabled = config?.hemis_enabled ?? true

  return (
    <div className="grid min-h-dvh lg:grid-cols-[1.15fr_1fr]">
      <section className="relative isolate hidden overflow-hidden bg-ink-900 text-white lg:flex lg:flex-col lg:justify-between lg:p-14 xl:p-20">
        <Ornament className="absolute inset-0 -z-10 h-full w-full text-azure-300/[0.07] [mask-image:linear-gradient(160deg,black_20%,transparent_75%)]" />
        <div className="flex items-center gap-3">
          <span className="rounded-[0.7rem] bg-white p-2">
            <Logo className="size-9" />
          </span>
          <span className="text-sm leading-snug text-ink-200">
            {t('Berdaq nomidagi')}
            <br />
            {t('Qoraqalpoq davlat universiteti')}
          </span>
        </div>

        <div className="max-w-xl">
          <h1 className="font-display text-[3.25rem] leading-[1.02] font-semibold tracking-[-0.02em] xl:text-[4rem]">
            {t('Masofaviy')}
            <br />
            {t('taʼlim tizimi')}
          </h1>
          <p className="mt-6 max-w-md text-lg leading-relaxed text-ink-200">
            {t('Sirtqi va masofaviy guruhlar uchun Zoom darslarini yaratish, boshlash va nazorat qilish.')}
          </p>
        </div>

        <ol className="grid max-w-2xl grid-cols-3 gap-6 border-t border-white/10 pt-8">
          {STEPS.map((step, index) => (
            <li key={step.title}>
              <span className="font-display text-sm text-azure-300">{index + 1}</span>
              <p className="mt-2 font-medium">{t(step.title)}</p>
              <p className="mt-1 text-sm leading-relaxed text-ink-300">{t(step.text)}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="relative flex flex-col justify-center px-5 py-12 sm:px-10">
        <LanguageSwitch className="absolute top-5 right-5" />
        <div className="mx-auto w-full max-w-sm">
          <div className="mb-10 flex items-center gap-3 lg:hidden">
            <Logo className="size-11" />
            <div>
              <p className="font-display text-lg font-semibold">{t('Masofaviy taʼlim')}</p>
              <p className="text-sm text-steel-600">{t('Qoraqalpoq davlat universiteti')}</p>
            </div>
          </div>

          <h2 className="text-2xl font-semibold tracking-tight text-ink-900">{t('Tizimga kirish')}</h2>
          <p className="mt-2 text-[0.9375rem] leading-relaxed text-steel-600">
            {t('Universitetning HEMIS hisobingiz bilan kiring. Birinchi kirishda hisobingizni administrator tasdiqlaydi.')}
          </p>

          {error && (
            <div role="alert" className="mt-6 flex gap-3 rounded-field bg-onair-50 p-4 text-sm text-onair-600">
              <AlertCircle className="mt-0.5 size-4 shrink-0" />
              <p>{t(CALLBACK_ERRORS[error] ?? CALLBACK_ERRORS.hemis_failed)}</p>
            </div>
          )}

          <div className="mt-8">
            {hemisEnabled ? (
              <Button
                size="lg"
                className="w-full"
                icon={<LogIn className="size-5" />}
                onClick={() => {
                  window.location.href = '/auth/hemis/redirect'
                }}
              >
                {t('HEMIS orqali kirish')}
              </Button>
            ) : (
              <p className="rounded-field bg-amber-50 p-4 text-sm text-amber-600">
                {t('HEMIS orqali kirish hali sozlanmagan. Administrator .env faylida HEMIS_* sozlamalarini kiritishi kerak.')}
              </p>
            )}
          </div>

          <div className="mt-3">
            {adminMode ? (
              <div className="mt-7 border-t border-steel-200 pt-6">
                <AdminLoginForm />
              </div>
            ) : (
              <Button size="lg" variant="secondary" className="w-full" icon={<KeyRound className="size-5" />} onClick={() => setAdminMode(true)}>
                {t('Login orqali kirish')}
              </Button>
            )}
          </div>
        </div>
      </section>
    </div>
  )
}
