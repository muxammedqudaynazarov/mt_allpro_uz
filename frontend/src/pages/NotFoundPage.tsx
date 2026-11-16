import { Link } from 'react-router'
import { buttonClass } from '@/components/ui/Button'

export default function NotFoundPage() {
  return (
    <div className="flex flex-col items-center py-24 text-center">
      <p className="font-display text-6xl font-semibold text-ink-200">404</p>
      <h1 className="mt-4 text-xl font-semibold">Sahifa topilmadi</h1>
      <p className="mt-2 text-steel-600">Manzil notoʻgʻri yoki sahifa oʻchirilgan.</p>
      <Link to="/" className={buttonClass({ className: 'mt-6' })}>
        Bosh sahifaga qaytish
      </Link>
    </div>
  )
}
