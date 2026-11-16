import { forwardRef, useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from './cn'

const control =
  'block w-full rounded-field bg-white px-3.5 text-[0.9375rem] text-ink-900 ring-1 ring-inset ring-steel-200 placeholder:text-steel-400 transition-shadow focus:outline-none focus:ring-2 focus:ring-azure-500 disabled:bg-steel-50 disabled:text-steel-500'

interface FieldShellProps {
  label?: ReactNode
  hint?: ReactNode
  error?: string
  id: string
  children: ReactNode
  className?: string
}

export function FieldShell({ label, hint, error, id, children, className }: FieldShellProps) {
  return (
    <div className={cn('space-y-1.5', className)}>
      {label && (
        <label htmlFor={id} className="block text-sm font-medium text-ink-800">
          {label}
        </label>
      )}
      {children}
      {error ? (
        <p id={`${id}-error`} className="text-sm text-onair-600">
          {error}
        </p>
      ) : (
        hint && <p className="text-sm text-steel-600">{hint}</p>
      )}
    </div>
  )
}

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: ReactNode
  hint?: ReactNode
  error?: string
  wrapperClassName?: string
  leading?: ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { label, hint, error, wrapperClassName, className, id, leading, ...props },
  ref,
) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <FieldShell label={label} hint={hint} error={error} id={inputId} className={wrapperClassName}>
      <div className="relative">
        {leading && <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-steel-500">{leading}</span>}
        <input
          ref={ref}
          id={inputId}
          aria-invalid={!!error}
          aria-describedby={error ? `${inputId}-error` : undefined}
          className={cn(control, 'h-11', leading && 'pl-10', error && 'ring-onair-500', className)}
          {...props}
        />
      </div>
    </FieldShell>
  )
})

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: ReactNode
  hint?: ReactNode
  error?: string
  wrapperClassName?: string
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { label, hint, error, wrapperClassName, className, id, children, ...props },
  ref,
) {
  const autoId = useId()
  const selectId = id ?? autoId
  return (
    <FieldShell label={label} hint={hint} error={error} id={selectId} className={wrapperClassName}>
      <div className="relative">
        <select
          ref={ref}
          id={selectId}
          aria-invalid={!!error}
          className={cn(control, 'h-11 appearance-none pr-10', error && 'ring-onair-500', className)}
          {...props}
        >
          {children}
        </select>
        <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-steel-500" aria-hidden />
      </div>
    </FieldShell>
  )
})

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: ReactNode
  hint?: ReactNode
  error?: string
  wrapperClassName?: string
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { label, hint, error, wrapperClassName, className, id, ...props },
  ref,
) {
  const autoId = useId()
  const areaId = id ?? autoId
  return (
    <FieldShell label={label} hint={hint} error={error} id={areaId} className={wrapperClassName}>
      <textarea
        ref={ref}
        id={areaId}
        aria-invalid={!!error}
        className={cn(control, 'min-h-24 py-2.5', error && 'ring-onair-500', className)}
        {...props}
      />
    </FieldShell>
  )
})
