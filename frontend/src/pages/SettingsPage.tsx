import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { GraduationCap, HardDrive, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { dayDate, formatBytes, formatDate } from '@/lib/format'
import type { AssessmentCriteria, AssessmentLevel, RecordingSettings, Semester, SystemSettings } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'
import { LEVEL_LABELS, LEVELS, LevelBadge } from '@/features/reports/SemesterReport'
import { useT } from '@/i18n/I18nProvider'

type Threshold = Exclude<AssessmentLevel, 'past'>
const THRESHOLDS: Threshold[] = ['sust', 'qoniqarli', 'ortacha', 'yaxshi', 'alo']

function SemesterModal({ semester, onClose }: { semester: Semester | 'new'; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const editing = semester !== 'new'
  const [form, setForm] = useState({
    name: editing ? semester.name : '',
    starts_on: editing ? semester.starts_on : '',
    ends_on: editing ? semester.ends_on : '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async () => (editing ? api.put(`/api/semesters/${semester.id}`, form) : api.post('/api/semesters', form)),
    onSuccess: () => {
      toast.success(t('Semestr saqlandi'))
      queryClient.invalidateQueries({ queryKey: ['semesters'] })
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
    setErrors({})
    save.mutate()
  }
  return (
    <Modal
      open
      onClose={onClose}
      title={editing ? t('Semestrni tahrirlash') : t('Semestr qoʻshish')}
      description={t('Semestr hisoboti shu sanalar oraligʻidagi darslar boʻyicha tuziladi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="semester-form" loading={save.isPending}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      <form id="semester-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <Input
          wrapperClassName="sm:col-span-2"
          label={t('Nomi')}
          placeholder={t('Masalan: 2026–2027, 1-semestr')}
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          error={errors.name}
          required
        />
        <Input
          label={t('Boshlanish sanasi')}
          type="date"
          value={form.starts_on}
          onChange={(e) => setForm({ ...form, starts_on: e.target.value })}
          error={errors.starts_on}
          required
        />
        <Input
          label={t('Tugash sanasi')}
          type="date"
          min={form.starts_on}
          value={form.ends_on}
          onChange={(e) => setForm({ ...form, ends_on: e.target.value })}
          error={errors.ends_on}
          required
        />
      </form>
    </Modal>
  )
}

function SemestersPanel() {
  const t = useT()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Semester | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Semester | null>(null)
  const semesters = useQuery({
    queryKey: ['semesters'],
    queryFn: async () => (await api.get<{ data: Semester[] }>('/api/semesters')).data.data,
  })
  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/semesters/${id}`),
    onSuccess: () => {
      toast.success(t('Semestr oʻchirildi'))
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['semesters'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <Panel
      padded={false}
      title={t('Semestrlar')}
      actions={
        <Button size="sm" variant="secondary" icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
          {t('Qoʻshish')}
        </Button>
      }
    >
      {semesters.isPending ? (
        <SectionLoader />
      ) : !semesters.data?.length ? (
        <EmptyState
          icon={<GraduationCap className="size-5" />}
          title={t('Semestrlar yoʻq')}
          description={t('Semestr sanalarini kiriting — semestr yakuni hisoboti shu sanalar boʻyicha tuziladi.')}
        />
      ) : (
        <ul className="divide-y divide-steel-100">
          {semesters.data.map((item) => (
            <li key={item.id} className="flex items-center gap-4 px-5 py-3">
              <div className="min-w-0 flex-1">
                <p className="flex items-center gap-2 font-medium text-ink-900">
                  {item.name}
                  {item.current && <Badge tone="leaf">{t('Joriy')}</Badge>}
                </p>
                <p className="text-sm text-steel-600 tabular-nums">
                  {formatDate(dayDate(item.starts_on))} – {formatDate(dayDate(item.ends_on))}
                </p>
              </div>
              <ActionMenu
                items={[
                  { label: t('Tahrirlash'), icon: <Pencil className="size-4" />, onClick: () => setEditing(item) },
                  { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(item) },
                ]}
              />
            </li>
          ))}
        </ul>
      )}
      {editing && <SemesterModal semester={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title={t('Semestrni oʻchirasizmi?')}
        description={t('Darslar va davomat oʻchirilmaydi, faqat semestr sanalari oʻchadi.')}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </Panel>
  )
}

const SCALE: Record<AssessmentLevel, string> = {
  past: 'bg-onair-500',
  sust: 'bg-amber-500',
  qoniqarli: 'bg-steel-400',
  ortacha: 'bg-azure-300',
  yaxshi: 'bg-azure-500',
  alo: 'bg-leaf-500',
}

/** 0–100% shkala: har bir daraja qaysi oraliqni egallashi. */
function ScalePreview({ thresholds }: { thresholds: AssessmentCriteria['thresholds'] }) {
  const t = useT()
  const starts: Record<AssessmentLevel, number> = { past: 0, ...thresholds }
  return (
    <div>
      <div className="flex h-3 overflow-hidden rounded-full" aria-hidden>
        {LEVELS.map((level, index) => {
          const from = Math.min(100, Math.max(0, starts[level]))
          const to = index < LEVELS.length - 1 ? Math.min(100, Math.max(from, starts[LEVELS[index + 1]])) : 100
          return <span key={level} className={SCALE[level]} style={{ width: `${to - from}%` }} />
        })}
      </div>
      <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-steel-600">
        {LEVELS.map((level, index) => (
          <span key={level} className="tabular-nums">
            {t(LEVEL_LABELS[level])}: {starts[level]}–{index < LEVELS.length - 1 ? starts[LEVELS[index + 1]] - 1 : 100}%
          </span>
        ))}
      </div>
    </div>
  )
}

function AssessmentPanel() {
  const t = useT()
  const queryClient = useQueryClient()
  const settings = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get<{ data: SystemSettings }>('/api/settings')).data.data,
  })
  const [form, setForm] = useState<AssessmentCriteria | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  useEffect(() => {
    if (settings.data && !form) setForm(settings.data.assessment)
  }, [settings.data, form])

  const save = useMutation({
    mutationFn: async (value: AssessmentCriteria) => (await api.put<{ data: SystemSettings }>('/api/settings', { assessment: value })).data.data,
    onSuccess: (data) => {
      toast.success(t('Mezon saqlandi'))
      queryClient.setQueryData(['settings'], data)
      queryClient.invalidateQueries({ queryKey: ['semester-report'] })
      queryClient.invalidateQueries({ queryKey: ['semester-overview'] })
      setForm(data.assessment)
    },
    onError: (error) => {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) toast.error(errorMessage(error))
    },
  })

  if (!form || !settings.data) return <SectionLoader />
  const setThreshold = (level: Threshold, value: string) => setForm({ ...form, thresholds: { ...form.thresholds, [level]: Number(value) } })

  return (
    <Panel title={t('Oʻzlashtirish taxmini mezoni')}>
      <form
        onSubmit={(event) => {
          event.preventDefault()
          setErrors({})
          save.mutate(form)
        }}
        className="space-y-6"
      >
        <p className="max-w-2xl text-sm text-steel-600">
          {t('Semestr hisobotida har bir talabaga Zoom’dagi qatnashuvi boʻyicha daraja beriladi. Bu baho emas — faqat qatnashuvdan chiqarilgan taxmin.')}
        </p>

        <fieldset>
          <legend className="text-sm font-medium text-ink-800">{t('Nima boʻyicha hisoblansin')}</legend>
          <div className="mt-2 grid gap-2.5 sm:grid-cols-2">
            {(
              [
                { value: 'time', title: t('Jami qatnashgan vaqt'), hint: t('Talaba Zoom’da boʻlgan vaqt ÷ barcha darslar davomiyligi.') },
                { value: 'lessons', title: t('Qatnashgan darslar soni'), hint: t('Talaba qatnashgan darslar ÷ oʻtkazilgan darslar.') },
              ] as const
            ).map((option) => (
              <label
                key={option.value}
                className={cn(
                  'flex cursor-pointer gap-3 rounded-field p-3.5 ring-1 ring-inset',
                  form.basis === option.value ? 'bg-azure-50 ring-2 ring-azure-500' : 'bg-white ring-steel-200 hover:ring-steel-300',
                )}
              >
                <input
                  type="radio"
                  name="basis"
                  className="mt-1 accent-azure-500"
                  checked={form.basis === option.value}
                  onChange={() => setForm({ ...form, basis: option.value })}
                />
                <span>
                  <span className="block text-sm font-medium text-ink-900">{option.title}</span>
                  <span className="block text-sm text-steel-600">{option.hint}</span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>

        <Input
          wrapperClassName="sm:w-80"
          type="number"
          min={1}
          max={100}
          label={t('Darsda qatnashgan hisoblanish chegarasi, %')}
          value={form.presence_percent}
          onChange={(e) => setForm({ ...form, presence_percent: Number(e.target.value) })}
          error={errors['assessment.presence_percent']}
          hint={t('Talaba darsning kamida shuncha foizida boʻlsa — shu darsga qatnashgan hisoblanadi.')}
        />

        <fieldset>
          <legend className="text-sm font-medium text-ink-800">{t('Darajalar chegarasi (foizdan boshlab)')}</legend>
          <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-5">
            {THRESHOLDS.map((level) => (
              <div key={level}>
                <label htmlFor={`threshold-${level}`} className="mb-1.5 block">
                  <LevelBadge level={level} />
                </label>
                <div className="relative">
                  <input
                    id={`threshold-${level}`}
                    type="number"
                    min={0}
                    max={100}
                    value={form.thresholds[level]}
                    onChange={(e) => setThreshold(level, e.target.value)}
                    aria-invalid={!!errors[`assessment.thresholds.${level}`]}
                    className={cn(
                      'h-11 w-full rounded-field bg-white px-3.5 pr-8 text-sm text-ink-900 tabular-nums ring-1 ring-steel-200 ring-inset focus:ring-2 focus:ring-azure-500 focus:outline-none',
                      errors[`assessment.thresholds.${level}`] && 'ring-onair-500',
                    )}
                  />
                  <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-steel-500">%</span>
                </div>
              </div>
            ))}
          </div>
          {THRESHOLDS.map(
            (level) =>
              errors[`assessment.thresholds.${level}`] && (
                <p key={level} className="mt-2 text-sm text-onair-600">
                  {errors[`assessment.thresholds.${level}`]}
                </p>
              ),
          )}
          <p className="mt-2 text-sm text-steel-600">{t('Hech bir chegaraga yetmagan talaba — “Past”.')}</p>
        </fieldset>

        <ScalePreview thresholds={form.thresholds} />

        <div className="flex flex-wrap gap-2 border-t border-steel-100 pt-5">
          <Button type="submit" loading={save.isPending}>
            {t('Saqlash')}
          </Button>
          <Button variant="secondary" onClick={() => setForm(settings.data.assessment_defaults)}>
            {t('Standart qiymatlar')}
          </Button>
        </div>
      </form>
    </Panel>
  )
}

/** Sozlama qatori: nomi, izohi va almashtirgich (komponent tashqarida — fokus saqlanadi). */
function SettingRow({ title, hint, checked, onChange, disabled }: { title: string; hint: string; checked: boolean; onChange: (value: boolean) => void; disabled?: boolean }) {
  return (
    <div className={cn('flex items-start justify-between gap-6 py-4', disabled && 'opacity-55')}>
      <div>
        <p className="text-sm font-medium text-ink-900">{title}</p>
        <p className="mt-0.5 max-w-xl text-sm text-steel-600">{hint}</p>
      </div>
      <Switch checked={checked} disabled={disabled} onChange={onChange} label={title} />
    </div>
  )
}

function RecordingPanel() {
  const t = useT()
  const queryClient = useQueryClient()
  const settings = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get<{ data: SystemSettings }>('/api/settings')).data.data,
  })
  const save = useMutation({
    mutationFn: async (value: RecordingSettings) => (await api.put<{ data: SystemSettings }>('/api/settings', { recording: value })).data.data,
    onSuccess: (data) => {
      toast.success(t('Sozlama saqlandi'))
      queryClient.setQueryData(['settings'], data)
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  if (!settings.data) return <SectionLoader />
  const value = settings.data.recording
  const storage = settings.data.storage
  const update = (patch: Partial<RecordingSettings>) => save.mutate({ ...value, ...patch })
  const lowSpace = storage.free_bytes !== null && storage.free_bytes < storage.min_free_gb * 1024 ** 3

  return (
    <Panel title={t('Video yozuvlar')}>
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div className="divide-y divide-steel-100">
          <SettingRow
            title={t('Darslarni avtomatik yozib olish')}
            hint={t('Yangi yaratiladigan darslar Zoom bulutiga yoziladi. Zoom akkauntida bulutga yozish yoqilgan boʻlishi kerak.')}
            checked={value.enabled}
            disabled={save.isPending}
            onChange={(enabled) => update({ enabled })}
          />
          <SettingRow
            title={t('Serverga koʻchirgach Zoom’dan oʻchirish')}
            hint={t('Yozuv serverga toʻliq yuklanib, hajmi tekshirilgach Zoom bulutidan oʻchiriladi — bulut xotirasi yangi darslar uchun boʻshaydi.')}
            checked={value.delete_from_zoom}
            disabled={save.isPending}
            onChange={(delete_from_zoom) => update({ delete_from_zoom })}
          />
          <fieldset className={cn('py-4', !value.delete_from_zoom && 'opacity-55')} disabled={!value.delete_from_zoom || save.isPending}>
            <legend className="text-sm font-medium text-ink-900">{t('Zoom’dan qanday oʻchirilsin')}</legend>
            <div className="mt-2 grid gap-2.5 sm:grid-cols-2">
              {(
                [
                  { value: 'trash', title: t('Savatga (tavsiya etiladi)'), hint: t('30 kun ichida Zoom’da tiklash mumkin. Savatdagi fayllar bulut hajmiga hisoblanmaydi.') },
                  { value: 'delete', title: t('Butunlay oʻchirish'), hint: t('Zoom’da tiklab boʻlmaydi — yagona nusxa serverda qoladi.') },
                ] as const
              ).map((option) => (
                <label
                  key={option.value}
                  className={cn(
                    'flex cursor-pointer gap-3 rounded-field p-3.5 ring-1 ring-inset',
                    value.delete_action === option.value ? 'bg-azure-50 ring-2 ring-azure-500' : 'bg-white ring-steel-200 hover:ring-steel-300',
                  )}
                >
                  <input
                    type="radio"
                    name="delete_action"
                    className="mt-1 accent-azure-500"
                    checked={value.delete_action === option.value}
                    onChange={() => update({ delete_action: option.value })}
                  />
                  <span>
                    <span className="block text-sm font-medium text-ink-900">{option.title}</span>
                    <span className="block text-sm text-steel-600">{option.hint}</span>
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
          <SettingRow
            title={t('Alohida audio faylni ham saqlash')}
            hint={t('Video faylda ovoz bor. Alohida M4A fayl faqat kerak boʻlsa saqlansin — oʻchiq boʻlsa, Zoom’dan oʻchirilganda u ham oʻchadi.')}
            checked={value.include_audio}
            disabled={save.isPending}
            onChange={(include_audio) => update({ include_audio })}
          />
        </div>

        <aside className="rounded-field bg-mist/70 p-4 text-sm">
          <p className="flex items-center gap-2 font-medium text-ink-900">
            <HardDrive className="size-4 text-steel-500" />
            {t('Server xotirasi')}
          </p>
          <dl className="mt-3 space-y-2">
            <div className="flex justify-between gap-3">
              <dt className="text-steel-600">{t('Saqlangan')}</dt>
              <dd className="tabular-nums">{t('{size}, {count} ta fayl', { size: formatBytes(storage.used_bytes), count: storage.files })}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-steel-600">{t('Boʻsh joy')}</dt>
              <dd className={cn('tabular-nums', lowSpace && 'font-medium text-onair-600')}>{storage.free_bytes !== null ? formatBytes(storage.free_bytes) : '—'}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-steel-600">{t('Zoom bulutida')}</dt>
              <dd className="tabular-nums">{formatBytes(storage.zoom_bytes)}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-steel-600">{t('Navbatda / xato')}</dt>
              <dd className="tabular-nums">
                {storage.pending} / <span className={cn(storage.failed > 0 && 'text-amber-600')}>{storage.failed}</span>
              </dd>
            </div>
          </dl>
          <p className="mt-3 border-t border-steel-200 pt-3 text-xs break-all text-steel-500">{storage.path}</p>
          {lowSpace && <p className="mt-2 text-xs text-onair-600">{t('Joy {gb} GB dan kam — yangi yozuvlar yuklanmaydi.', { gb: storage.min_free_gb })}</p>}
        </aside>
      </div>
    </Panel>
  )
}

export default function SettingsPage() {
  const t = useT()
  return (
    <>
      <PageHeader title={t('Umumiy sozlamalar')} description={t('Semestr sanalari, oʻzlashtirish taxmini mezoni va darslarning video yozuvlari.')} />
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)] xl:items-start">
        <SemestersPanel />
        <AssessmentPanel />
      </div>
      <div className="mt-6">
        <RecordingPanel />
      </div>
    </>
  )
}
