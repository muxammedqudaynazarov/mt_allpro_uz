import { useState } from 'react'
import { initials } from '@/lib/format'
import { cn } from './cn'

export function Avatar({ name, src, className }: { name: string; src?: string | null; className?: string }) {
  const [failed, setFailed] = useState(false)
  if (src && !failed) {
    return <img src={src} alt="" onError={() => setFailed(true)} className={cn('size-9 shrink-0 rounded-full object-cover ring-1 ring-steel-200', className)} />
  }
  return (
    <span
      aria-hidden
      className={cn('inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-ink-100 text-xs font-semibold text-ink-700', className)}
    >
      {initials(name)}
    </span>
  )
}
