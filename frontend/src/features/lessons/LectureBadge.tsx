import { Presentation } from 'lucide-react'
import { cn } from '@/components/ui/cn'
import { useT } from '@/i18n/I18nProvider'

/** Ma'ruza belgisi: bir uchrashuvda bir nechta guruh. */
export function LectureBadge({ className }: { className?: string }) {
  const t = useT()
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-xs font-medium whitespace-nowrap text-ink-700 ring-1 ring-ink-200 ring-inset',
        className,
      )}
    >
      <Presentation className="size-3.5" aria-hidden />
      {t('Maʼruza')}
    </span>
  )
}
