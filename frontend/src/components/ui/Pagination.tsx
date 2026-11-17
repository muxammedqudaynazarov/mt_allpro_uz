import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'
import { useT } from '@/i18n/I18nProvider'

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  onChange: (page: number) => void
}

export function Pagination({ page, lastPage, total, onChange }: PaginationProps) {
  const t = useT()
  if (lastPage <= 1) return null
  return (
    <nav className="flex items-center justify-between gap-3 border-t border-steel-100 px-5 py-3" aria-label={t('Sahifalar')}>
      <p className="text-sm text-steel-600">
        {t('Jami {total} ta, {page}/{last}-sahifa', { total: total.toLocaleString('ru-RU'), page, last: lastPage })}
      </p>
      <div className="flex gap-2">
        <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)} icon={<ChevronLeft className="size-4" />}>
          {t('Oldingi')}
        </Button>
        <Button variant="secondary" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>
          {t('Keyingi')}
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </nav>
  )
}
