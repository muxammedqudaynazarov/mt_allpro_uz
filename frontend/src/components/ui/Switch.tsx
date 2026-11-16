import { Switch as HSwitch } from '@headlessui/react'
import { cn } from './cn'

interface SwitchProps {
  checked: boolean
  onChange: (value: boolean) => void
  label: string
  disabled?: boolean
}

export function Switch({ checked, onChange, label, disabled }: SwitchProps) {
  return (
    <HSwitch
      checked={checked}
      onChange={onChange}
      disabled={disabled}
      aria-label={label}
      className={cn(
        'group relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
        'bg-steel-200 data-checked:bg-leaf-500 data-disabled:cursor-not-allowed data-disabled:opacity-50',
      )}
    >
      <span className="size-5 translate-x-0.5 rounded-full bg-white shadow-sm transition-transform group-data-checked:translate-x-5.5" />
    </HSwitch>
  )
}
