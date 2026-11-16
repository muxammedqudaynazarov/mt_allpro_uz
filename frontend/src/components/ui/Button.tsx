import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from './cn'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'onair' | 'quiet'
type Size = 'sm' | 'md' | 'lg'

const variants: Record<Variant, string> = {
  primary: 'bg-azure-500 text-white hover:bg-azure-600 active:bg-azure-700 shadow-[0_1px_0_0_rgb(255_255_255/0.18)_inset,0_1px_2px_rgb(19_40_74/0.25)]',
  secondary: 'bg-white text-ink-900 ring-1 ring-inset ring-steel-200 hover:bg-steel-50 hover:ring-steel-300',
  ghost: 'text-ink-700 hover:bg-ink-50',
  quiet: 'text-azure-600 hover:text-azure-700 hover:underline underline-offset-4 px-0!',
  danger: 'bg-white text-onair-600 ring-1 ring-inset ring-onair-100 hover:bg-onair-50',
  onair: 'bg-onair-500 text-white hover:bg-onair-600',
}

const sizes: Record<Size, string> = {
  sm: 'h-8 px-3 text-sm gap-1.5',
  md: 'h-10 px-4 text-sm gap-2',
  lg: 'h-12 px-5 text-base gap-2.5',
}

export function buttonClass({ variant = 'primary', size = 'md', className }: { variant?: Variant; size?: Size; className?: string } = {}) {
  return cn(
    'inline-flex shrink-0 items-center justify-center rounded-field font-medium whitespace-nowrap transition-colors',
    'disabled:cursor-not-allowed disabled:opacity-55',
    variants[variant],
    sizes[size],
    className,
  )
}

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  icon?: ReactNode
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading = false, icon, className, children, disabled, type = 'button', ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      className={buttonClass({ variant, size, className })}
      {...props}
    >
      {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : icon}
      {children}
    </button>
  )
})
