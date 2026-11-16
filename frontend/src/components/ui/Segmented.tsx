import { cn } from './cn'

interface Option<T extends string> {
  value: T
  label: string
  count?: number | null
}

interface SegmentedProps<T extends string> {
  value: T
  onChange: (value: T) => void
  options: Option<T>[]
  label: string
  className?: string
}

/** Filtr uchun tugmalar guruhi (tablar). */
export function Segmented<T extends string>({ value, onChange, options, label, className }: SegmentedProps<T>) {
  return (
    <div role="tablist" aria-label={label} className={cn('inline-flex max-w-full gap-1 overflow-x-auto rounded-field bg-steel-100 p-1', className)}>
      {options.map((option) => {
        const active = option.value === value
        return (
          <button
            key={option.value}
            role="tab"
            type="button"
            aria-selected={active}
            onClick={() => onChange(option.value)}
            className={cn(
              'inline-flex h-8 items-center gap-2 rounded-[0.5rem] px-3 text-sm font-medium whitespace-nowrap transition-colors',
              active ? 'bg-white text-ink-900 shadow-[0_1px_2px_rgb(19_40_74/0.12)]' : 'text-steel-600 hover:text-ink-900',
            )}
          >
            {option.label}
            {option.count ? (
              <span className={cn('rounded-full px-1.5 text-xs tabular-nums', active ? 'bg-azure-50 text-azure-700' : 'bg-steel-200 text-steel-700')}>
                {option.count}
              </span>
            ) : null}
          </button>
        )
      })}
    </div>
  )
}
