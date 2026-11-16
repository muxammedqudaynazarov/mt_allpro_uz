import type { ReactNode } from 'react'
import { cn } from './cn'

export function PageHeader({ title, description, actions }: { title: ReactNode; description?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div className="min-w-0">
        <h1 className="font-display text-[1.65rem] leading-tight font-semibold tracking-[-0.01em] text-ink-900 sm:text-[1.875rem]">{title}</h1>
        {description && <p className="mt-2 max-w-2xl text-[0.9375rem] text-steel-600">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  )
}

export function Panel({ children, className, title, actions, padded = true }: { children: ReactNode; className?: string; title?: ReactNode; actions?: ReactNode; padded?: boolean }) {
  return (
    <section className={cn('min-w-0 rounded-panel bg-white ring-1 ring-steel-200/70', className)}>
      {(title || actions) && (
        <header className="flex items-center justify-between gap-3 border-b border-steel-100 px-5 py-4">
          {title && <h2 className="text-[0.9375rem] font-semibold text-ink-900">{title}</h2>}
          {actions}
        </header>
      )}
      <div className={cn(padded && 'p-5')}>{children}</div>
    </section>
  )
}
