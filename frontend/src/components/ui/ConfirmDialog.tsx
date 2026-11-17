import type { ReactNode } from 'react'
import { Button } from './Button'
import { Modal } from './Modal'
import { useT } from '@/i18n/I18nProvider'

interface ConfirmDialogProps {
  open: boolean
  title: ReactNode
  description?: ReactNode
  confirmLabel: string
  tone?: 'primary' | 'danger'
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({ open, title, description, confirmLabel, tone = 'primary', loading, onConfirm, onClose }: ConfirmDialogProps) {
  const t = useT()
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button variant={tone === 'danger' ? 'onair' : 'primary'} loading={loading} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      {description && <p className="text-[0.9375rem] leading-relaxed text-steel-700">{description}</p>}
    </Modal>
  )
}
