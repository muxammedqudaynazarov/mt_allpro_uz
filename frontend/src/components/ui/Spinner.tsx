import { Loader2 } from 'lucide-react'
import { cn } from './cn'
import { Logo } from './Logo'
import { useT } from '@/i18n/I18nProvider'

export function Spinner({ className }: { className?: string }) {
  const t = useT()
  return <Loader2 className={cn('size-5 animate-spin text-azure-500', className)} aria-label={t('Yuklanmoqda')} />
}

export function FullPageLoader() {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-5" role="status" aria-live="polite">
      <Logo className="size-12" />
      <Spinner />
    </div>
  )
}

export function SectionLoader({ className }: { className?: string }) {
  return (
    <div className={cn('flex items-center justify-center py-16', className)} role="status" aria-live="polite">
      <Spinner />
    </div>
  )
}
