import type { ReactNode } from 'react'
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { X } from 'lucide-react'
import { cn } from './cn'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: ReactNode
  description?: ReactNode
  children: ReactNode
  footer?: ReactNode
  size?: 'sm' | 'md' | 'lg'
}

const sizes = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl' }

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: ModalProps) {
  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      <DialogBackdrop
        transition
        className="fixed inset-0 bg-ink-950/45 backdrop-blur-[2px] transition-opacity duration-200 data-closed:opacity-0"
      />
      <div className="fixed inset-0 overflow-y-auto">
        <div className="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-6">
          <DialogPanel
            transition
            className={cn(
              'w-full rounded-t-panel bg-white shadow-[0_24px_64px_-12px_rgb(12_27_51/0.35)] transition duration-200 sm:rounded-panel',
              'data-closed:translate-y-4 data-closed:opacity-0 sm:data-closed:translate-y-0 sm:data-closed:scale-[0.98]',
              sizes[size],
            )}
          >
            <div className="flex items-start justify-between gap-4 px-6 pt-6">
              <div className="space-y-1">
                <DialogTitle className="text-lg font-semibold text-ink-900">{title}</DialogTitle>
                {description && <p className="text-sm text-steel-600">{description}</p>}
              </div>
              <button
                type="button"
                onClick={onClose}
                className="-mt-1 -mr-2 rounded-field p-2 text-steel-500 hover:bg-steel-100 hover:text-ink-900"
                aria-label="Yopish"
              >
                <X className="size-5" />
              </button>
            </div>
            <div className="px-6 py-5">{children}</div>
            {footer && <div className="flex flex-col-reverse gap-2 border-t border-steel-100 px-6 py-4 sm:flex-row sm:justify-end">{footer}</div>}
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
