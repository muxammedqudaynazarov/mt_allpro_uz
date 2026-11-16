import { useId } from 'react'
import { cn } from './cn'

/**
 * Universitetning "MT" (Masofaviy Ta'lim) belgisi — logotipning soddalashtirilgan
 * vektor nusxasi. Rangli fon ustida ham, oq fonda ham yaxshi ko'rinadi.
 * Gradient identifikatorlari har nusxa uchun alohida: aks holda yashirin
 * (display:none) nusxa boshqalarining rangini "o'g'irlaydi".
 */
export function Logo({ className, onDark = false }: { className?: string; onDark?: boolean }) {
  const id = useId().replace(/:/g, '')
  return (
    <svg viewBox="0 0 64 64" className={cn('shrink-0', className)} role="img" aria-label="Masofaviy taʼlim">
      <defs>
        <linearGradient id={`${id}-m`} x1="0" x2="1">
          <stop offset="0" stopColor="#4f9be6" />
          <stop offset="1" stopColor={onDark ? '#7fb2ec' : '#1f4a86'} />
        </linearGradient>
        <linearGradient id={`${id}-t`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor={onDark ? '#dbe3f0' : '#13284a'} />
          <stop offset=".7" stopColor="#9aa6b4" />
        </linearGradient>
      </defs>
      <path d="M6 54V10h9l14 22 10-15h8L29 45 16 26v28z" fill={`url(#${id}-m)`} />
      <path d="M41 17h19v7H49v30h-8z" fill={`url(#${id}-t)`} />
    </svg>
  )
}
