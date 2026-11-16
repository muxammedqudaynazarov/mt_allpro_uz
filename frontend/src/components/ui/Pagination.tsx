import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  onChange: (page: number) => void
}

export function Pagination({ page, lastPage, total, onChange }: PaginationProps) {
  if (lastPage <= 1) return null
  return (
    <nav className="flex items-center justify-between gap-3 border-t border-steel-100 px-5 py-3" aria-label="Sahifalar">
      <p className="text-sm text-steel-600">
        Jami {total.toLocaleString('ru-RU')} ta, {page}/{lastPage}-sahifa
      </p>
      <div className="flex gap-2">
        <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)} icon={<ChevronLeft className="size-4" />}>
          Oldingi
        </Button>
        <Button variant="secondary" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>
          Keyingi
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </nav>
  )
}
