import type { ReactNode } from 'react'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react'
import { MoreHorizontal } from 'lucide-react'
import { useT } from '@/i18n/I18nProvider'
import { cn } from './cn'

export interface ActionItem {
  label: string
  icon?: ReactNode
  onClick: () => void
  danger?: boolean
  hidden?: boolean
}

export function ActionMenu({ items, label }: { items: ActionItem[]; label?: string }) {
  const t = useT()
  const visible = items.filter((item) => !item.hidden)
  if (!visible.length) return null
  return (
    <Menu>
      <MenuButton className="rounded-field p-2 text-steel-500 hover:bg-steel-100 hover:text-ink-900" aria-label={label ?? t('Amallar')}>
        <MoreHorizontal className="size-5" />
      </MenuButton>
      <MenuItems
        anchor="bottom end"
        transition
        className="z-40 min-w-52 rounded-field bg-white p-1 shadow-[0_12px_32px_-8px_rgb(12_27_51/0.3)] ring-1 ring-steel-200 transition duration-100 [--anchor-gap:6px] focus:outline-none data-closed:scale-95 data-closed:opacity-0"
      >
        {visible.map((item) => (
          <MenuItem key={item.label}>
            <button
              type="button"
              onClick={item.onClick}
              className={cn(
                'flex w-full items-center gap-2.5 rounded-[0.5rem] px-3 py-2 text-left text-sm data-focus:bg-steel-100',
                item.danger ? 'text-onair-600' : 'text-ink-800',
              )}
            >
              {item.icon}
              {item.label}
            </button>
          </MenuItem>
        ))}
      </MenuItems>
    </Menu>
  )
}
