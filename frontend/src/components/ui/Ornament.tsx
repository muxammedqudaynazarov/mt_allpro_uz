import { useId } from 'react'
import { cn } from './cn'

/**
 * Qoraqalpoq naqshidagi "qo'chqor shox" motiviga asoslangan takrorlanuvchi
 * bezak. Faqat kirish sahifasida, bir marta ishlatiladi.
 */
export function Ornament({ className }: { className?: string }) {
  const id = useId().replace(/:/g, '')
  return (
    <svg className={cn('pointer-events-none', className)} aria-hidden>
      <defs>
        <pattern id={`orn-${id}`} width="72" height="72" patternUnits="userSpaceOnUse">
          <g fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
            <path d="M36 20 52 36 36 52 20 36Z" />
            <path d="M36 20c-3-6-11-7-12-1 0 3 3 4 5 2M36 20c3-6 11-7 12-1 0 3-3 4-5 2" />
            <path d="M36 52c-3 6-11 7-12 1 0-3 3-4 5-2M36 52c3 6 11 7 12 1 0-3-3-4-5-2" />
            <path d="M20 36c-6-3-7-11-1-12 3 0 4 3 2 5M20 36c-6 3-7 11-1 12 3 0 4-3 2-5" />
            <path d="M52 36c6-3 7-11 1-12-3 0-4 3-2 5M52 36c6 3 7 11 1 12-3 0-4-3-2-5" />
          </g>
          <path d="M36 30 42 36 36 42 30 36Z" fill="currentColor" />
          <circle cx="0" cy="0" r="2" fill="currentColor" />
          <circle cx="72" cy="0" r="2" fill="currentColor" />
          <circle cx="0" cy="72" r="2" fill="currentColor" />
          <circle cx="72" cy="72" r="2" fill="currentColor" />
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill={`url(#orn-${id})`} />
    </svg>
  )
}
