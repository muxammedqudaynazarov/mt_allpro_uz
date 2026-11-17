import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock3, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { formatDuration } from '@/lib/format'
import type { LessonSlot } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'
import { useT } from '@/i18n/I18nProvider'

function SlotModal({ slot, onClose }: { slot: LessonSlot | 'new'; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const editing = slot !== 'new'
  const [form, setForm] = useState({
    name: editing ? slot.name : '',
    starts_at: editing ? slot.starts_at : '',
    duration_minutes: editing ? String(slot.duration_minutes) : '80',
    sort: editing ? String(slot.sort) : '0',
    is_active: editing ? slot.is_active : true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const save = useMutation({
    mutationFn: async () => {
      const payload = { ...form, duration_minutes: Number(form.duration_minutes), sort: Number(form.sort) }
      return editing ? api.put(`/api/lesson-slots/${slot.id}`, payload) : api.post('/api/lesson-slots', payload)
    },
    onSuccess: () => {
      toast.success(t('Dars vaqti saqlandi'))
      queryClient.invalidateQueries({ queryKey: ['lesson-slots'] })
      queryClient.invalidateQueries({ queryKey: ['lesson-options'] })
      onClose()
    },
    onError: (error) => {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) toast.error(errorMessage(error))
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title={editing ? t('Dars vaqtini tahrirlash') : t('Dars vaqti qoʻshish')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="slot-form" loading={save.isPending}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      <form id="slot-form" onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Input wrapperClassName="col-span-2" label={t('Nomi')} placeholder={t('1-para')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} required />
        <Input label={t('Boshlanishi')} type="time" value={form.starts_at} onChange={(e) => setForm({ ...form, starts_at: e.target.value })} error={errors.starts_at} required />
        <Input label={t('Davomiyligi, daqiqa')} type="number" min={10} max={240} value={form.duration_minutes} onChange={(e) => setForm({ ...form, duration_minutes: e.target.value })} error={errors.duration_minutes} required />
        <Input label={t('Tartib raqami')} type="number" min={0} value={form.sort} onChange={(e) => setForm({ ...form, sort: e.target.value })} error={errors.sort} />
        <label className="flex items-center gap-3 self-end pb-2.5">
          <Switch checked={form.is_active} onChange={(value) => setForm({ ...form, is_active: value })} label={t('Faol')} />
          <span className="text-sm font-medium">{t('Faol')}</span>
        </label>
      </form>
    </Modal>
  )
}

export default function SlotsPage() {
  const t = useT()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<LessonSlot | 'new' | null>(null)
  const [deleting, setDeleting] = useState<LessonSlot | null>(null)

  const slots = useQuery({
    queryKey: ['lesson-slots'],
    queryFn: async () => (await api.get<{ data: LessonSlot[] }>('/api/lesson-slots')).data.data,
  })

  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/lesson-slots/${id}`),
    onSuccess: () => {
      toast.success(t('Dars vaqti oʻchirildi'))
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['lesson-slots'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <PageHeader
        title={t('Dars vaqtlari')}
        description={t('Oʻqituvchilar dars yaratishda shu paralardan birini tanlaydi. Davomiylik Zoom uchrashuvining uzunligini belgilaydi.')}
        actions={
          <Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
            {t('Vaqt qoʻshish')}
          </Button>
        }
      />
      <Panel padded={false}>
        {slots.isPending ? (
          <SectionLoader />
        ) : !slots.data?.length ? (
          <EmptyState icon={<Clock3 className="size-5" />} title={t('Dars vaqtlari kiritilmagan')} description={t('Kamida bitta para qoʻshing. Ularsiz faqat “Hozir” rejimida dars yaratish mumkin.')} />
        ) : (
          <ol className="divide-y divide-steel-100">
            {slots.data.map((slot, index) => (
              <li key={slot.id} className="flex items-center gap-4 px-5 py-4">
                <span className="flex size-8 items-center justify-center rounded-full bg-ink-50 font-display text-xs font-semibold text-ink-700">{index + 1}</span>
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{slot.name}</p>
                  <p className="text-sm text-steel-600 tabular-nums">
                    {slot.starts_at}–{slot.ends_at}, {formatDuration(slot.duration_minutes)}
                  </p>
                </div>
                {!slot.is_active && <Badge>{t('Nofaol')}</Badge>}
                <ActionMenu
                  items={[
                    { label: t('Tahrirlash'), icon: <Pencil className="size-4" />, onClick: () => setEditing(slot) },
                    { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(slot) },
                  ]}
                />
              </li>
            ))}
          </ol>
        )}
      </Panel>
      {editing && <SlotModal slot={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title={t('Dars vaqtini oʻchirasizmi?')}
        description={t('Oldin shu vaqtda oʻtkazilgan darslar tarixda qoladi.')}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}
