import type { ReactNode } from 'react'
import { cn } from './cn'

interface EmptyStateProps {
  icon?: ReactNode
  title: string
  description?: ReactNode
  action?: ReactNode
  className?: string
}

export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center px-6 py-14 text-center', className)}>
      {icon && <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-ink-50 text-ink-500">{icon}</div>}
      <p className="text-base font-semibold text-ink-900">{title}</p>
      {description && <p className="mt-1.5 max-w-sm text-sm leading-relaxed text-steel-600">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}
