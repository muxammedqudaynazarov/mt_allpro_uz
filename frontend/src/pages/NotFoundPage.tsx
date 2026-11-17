import { Link } from 'react-router'
import { buttonClass } from '@/components/ui/Button'
import { useT } from '@/i18n/I18nProvider'

export default function NotFoundPage() {
  const t = useT()
  return (
    <div className="flex flex-col items-center py-24 text-center">
      <p className="font-display text-6xl font-semibold text-ink-200">404</p>
      <h1 className="mt-4 text-xl font-semibold">{t('Sahifa topilmadi')}</h1>
      <p className="mt-2 text-steel-600">{t('Manzil notoʻgʻri yoki sahifa oʻchirilgan.')}</p>
      <Link to="/" className={buttonClass({ className: 'mt-6' })}>
        {t('Bosh sahifaga qaytish')}
      </Link>
    </div>
  )
}
