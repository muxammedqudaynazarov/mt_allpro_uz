import type { ReactNode } from 'react'
import { cn } from './cn'

type Tone = 'neutral' | 'azure' | 'leaf' | 'amber' | 'onair' | 'ink'

const tones: Record<Tone, string> = {
  neutral: 'bg-steel-100 text-steel-700',
  azure: 'bg-azure-50 text-azure-700',
  leaf: 'bg-leaf-50 text-leaf-600',
  amber: 'bg-amber-50 text-amber-600',
  onair: 'bg-onair-50 text-onair-600',
  ink: 'bg-ink-900 text-white',
}

export function Badge({ tone = 'neutral', children, className, dot }: { tone?: Tone; children: ReactNode; className?: string; dot?: boolean }) {
  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', tones[tone], className)}>
      {dot && <span className="size-1.5 rounded-full bg-current" aria-hidden />}
      {children}
    </span>
  )
}

/** Efirdagi dars belgisi — qizil "ON AIR" chirog'i. */
export function OnAirDot({ className }: { className?: string }) {
  return (
    <span className={cn('relative inline-flex size-2.5', className)} aria-hidden>
      <span className="absolute inset-0 animate-onair rounded-full bg-onair-500" />
      <span className="relative inline-flex size-2.5 rounded-full bg-onair-500/70" />
    </span>
  )
}
