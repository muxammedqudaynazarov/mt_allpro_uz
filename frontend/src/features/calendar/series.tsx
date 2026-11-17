import { useMemo, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CalendarOff, CalendarX2, Repeat, RotateCcw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { dayDate, formatDayMonth, formatTime, formatWeekday, isoDate, weekdayName } from '@/lib/format'
import type { LessonOptions, LessonSeries, PlannedLesson } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { Panel } from '@/components/ui/Page'
import { useT } from '@/i18n/I18nProvider'

const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]

/** "dushanba" → "Dushanba" */
const capital = (text: string) => text.charAt(0).toLocaleUpperCase('uz') + text.slice(1)

function useSeriesActions() {
  const t = useT()
  const queryClient = useQueryClient()
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['calendar'] })
    queryClient.invalidateQueries({ queryKey: ['series'] })
    queryClient.invalidateQueries({ queryKey: ['lessons'] })
  }
  const onError = (error: unknown) => toast.error(errorMessage(error))

  return {
    skip: useMutation({
      mutationFn: async ({ id, date }: { id: number; date: string }) => api.post(`/api/series/${id}/skips`, { date }),
      onSuccess: () => {
        toast.success(t('Shu kungi dars bekor qilindi'))
        refresh()
      },
      onError,
    }),
    unskip: useMutation({
      mutationFn: async ({ id, date }: { id: number; date: string }) => api.delete(`/api/series/${id}/skips`, { data: { date } }),
      onSuccess: () => {
        toast.success(t('Dars qayta rejaga qoʻshildi'))
        refresh()
      },
      onError,
    }),
    cancel: useMutation({
      mutationFn: async (id: number) => api.post(`/api/series/${id}/cancel`),
      onSuccess: () => {
        toast.success(t('Haftalik dars toʻxtatildi'))
        refresh()
      },
      onError,
    }),
    remove: useMutation({
      mutationFn: async (id: number) => api.delete(`/api/series/${id}`),
      onSuccess: () => {
        toast.success(t('Haftalik dars oʻchirildi'))
        refresh()
      },
      onError,
    }),
  }
}

/** Haftalik dars qo'shish. Zoom uchrashuvi har hafta dars kuni, boshlanishidan 1 soat oldin yaratiladi. */
export function SeriesModal({ open, onClose, initial }: { open: boolean; onClose: () => void; initial?: { weekday?: number; slot?: number } }) {
  const t = useT()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    group_id: '',
    weekday: initial?.weekday ?? 1,
    lesson_slot_id: initial?.slot ? String(initial.slot) : '',
    zoom_host_id: '',
    topic: '',
    starts_on: isoDate(),
    ends_on: '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = <K extends keyof typeof form>(key: K, value: (typeof form)[K]) => setForm((current) => ({ ...current, [key]: value }))

  const options = useQuery({
    queryKey: ['lesson-options', 'series'],
    queryFn: async () => (await api.get<{ data: LessonOptions }>('/api/lessons/options')).data.data,
    enabled: open,
  })
  const courses = useMemo(() => [...new Set(options.data?.groups.map((group) => group.course))].sort((a, b) => a - b), [options.data])

  const save = useMutation({
    mutationFn: async () =>
      api.post('/api/series', {
        group_id: form.group_id ? Number(form.group_id) : null,
        weekday: form.weekday,
        lesson_slot_id: form.lesson_slot_id ? Number(form.lesson_slot_id) : null,
        zoom_host_id: form.zoom_host_id ? Number(form.zoom_host_id) : null,
        topic: form.topic.trim() || null,
        starts_on: form.starts_on || null,
        ends_on: form.ends_on || null,
      }),
    onSuccess: () => {
      toast.success(t('Haftalik dars qoʻshildi'))
      queryClient.invalidateQueries({ queryKey: ['calendar'] })
      queryClient.invalidateQueries({ queryKey: ['series'] })
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
      open={open}
      onClose={onClose}
      size="lg"
      title={t('Haftalik dars qoʻshish')}
      description={t('Zoom uchrashuvi har hafta dars kuni, boshlanishidan 1 soat oldin avtomatik yaratiladi va havola sizga Telegram orqali yuboriladi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="series-form" loading={save.isPending}>
            {t('Qoʻshish')}
          </Button>
        </>
      }
    >
      <form id="series-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <fieldset className="sm:col-span-2">
          <legend className="mb-1.5 text-sm font-medium text-ink-800">{t('Hafta kuni')}</legend>
          <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={t('Hafta kuni')}>
            {WEEKDAYS.map((day) => (
              <button
                key={day}
                type="button"
                role="radio"
                aria-checked={form.weekday === day}
                onClick={() => set('weekday', day)}
                className={cn(
                  'h-9 rounded-full px-3.5 text-sm font-medium transition-colors',
                  form.weekday === day ? 'bg-ink-900 text-white' : 'bg-white text-ink-800 ring-1 ring-steel-200 ring-inset hover:ring-steel-300',
                )}
              >
                {capital(weekdayName(day))}
              </button>
            ))}
          </div>
          {errors.weekday && <p className="mt-1.5 text-sm text-onair-600">{errors.weekday}</p>}
        </fieldset>
        <Select label={t('Juftlik')} value={form.lesson_slot_id} onChange={(e) => set('lesson_slot_id', e.target.value)} error={errors.lesson_slot_id} required>
          <option value="">{t('Tanlang')}</option>
          {options.data?.slots.map((slot) => (
            <option key={slot.id} value={slot.id}>
              {slot.name}, {slot.starts_at}–{slot.ends_at}
            </option>
          ))}
        </Select>
        <Select label={t('Guruh')} value={form.group_id} onChange={(e) => set('group_id', e.target.value)} error={errors.group_id} required>
          <option value="">{t('Tanlang')}</option>
          {courses.map((course) => (
            <optgroup key={course} label={t('{n}-kurs', { n: course })}>
              {options.data?.groups
                .filter((group) => group.course === course)
                .map((group) => (
                  <option key={group.id} value={group.id}>
                    {group.name}
                  </option>
                ))}
            </optgroup>
          ))}
        </Select>
        <Select
          wrapperClassName="sm:col-span-2"
          label={t('Zoom host')}
          value={form.zoom_host_id}
          onChange={(e) => set('zoom_host_id', e.target.value)}
          error={errors.zoom_host_id}
          hint={t('Tanlangan host oʻsha kuni band boʻlsa, boshqa boʻsh host olinadi.')}
        >
          <option value="">{t('Avtomatik (birinchi boʻsh host)')}</option>
          {options.data?.hosts
            .filter((host) => host.licensed)
            .map((host) => (
              <option key={host.id} value={host.id}>
                {host.name === host.email ? host.email : `${host.name} (${host.email})`}
              </option>
            ))}
        </Select>
        <Input
          wrapperClassName="sm:col-span-2"
          label={t('Mavzu')}
          value={form.topic}
          maxLength={200}
          onChange={(e) => set('topic', e.target.value)}
          error={errors.topic}
          hint={t('Ixtiyoriy')}
        />
        <Input label={t('Boshlanish sanasi')} type="date" min={isoDate()} value={form.starts_on} onChange={(e) => set('starts_on', e.target.value)} error={errors.starts_on} />
        <Input
          label={t('Tugash sanasi')}
          type="date"
          min={form.starts_on || isoDate()}
          value={form.ends_on}
          onChange={(e) => set('ends_on', e.target.value)}
          error={errors.ends_on}
          hint={t('Boʻsh qoldirilsa — bekor qilinguncha')}
        />
        {!user?.telegram.linked && (
          <p className="flex items-start gap-2 rounded-field bg-amber-50 p-3 text-sm text-amber-600 sm:col-span-2">
            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
            <span>
              {t('Telegram ulanmagan — havolalar sizga yuborilmaydi.')}{' '}
              <Link to="/profile" className="font-medium underline underline-offset-4">
                {t('Profilda ulash')}
              </Link>
            </span>
          </p>
        )}
      </form>
    </Modal>
  )
}

/** Rejadagi (uchrashuvi hali yaratilmagan) dars: bitta kunni yoki butun jadvalni bekor qilish. */
export function PlannedModal({ item, onClose, leadMinutes }: { item: PlannedLesson | null; onClose: () => void; leadMinutes: number }) {
  const t = useT()
  const { canViewAll } = useAuth()
  const actions = useSeriesActions()
  const [confirmStop, setConfirmStop] = useState(false)
  if (!item) return null
  const day = dayDate(item.date)
  const createdAt = new Date(new Date(item.starts_at).getTime() - leadMinutes * 60_000)

  return (
    <>
      <Modal
        open={!!item && !confirmStop}
        onClose={onClose}
        title={item.group.name}
        description={`${capital(formatWeekday(day))}, ${formatDayMonth(day)}. ${item.slot.name}, ${formatTime(item.starts_at)}–${formatTime(item.ends_at)}`}
        footer={
          item.can_cancel ? (
            <>
              <Button variant="danger" icon={<CalendarOff className="size-4" />} onClick={() => setConfirmStop(true)}>
                {t('Jadvalni toʻxtatish')}
              </Button>
              {item.skipped ? (
                <Button
                  icon={<RotateCcw className="size-4" />}
                  loading={actions.unskip.isPending}
                  onClick={() => actions.unskip.mutate({ id: item.series_id, date: item.date }, { onSuccess: onClose })}
                >
                  {t('Qayta rejaga qoʻshish')}
                </Button>
              ) : (
                <Button
                  icon={<CalendarX2 className="size-4" />}
                  loading={actions.skip.isPending}
                  onClick={() => actions.skip.mutate({ id: item.series_id, date: item.date }, { onSuccess: onClose })}
                >
                  {t('Shu kunni bekor qilish')}
                </Button>
              )}
            </>
          ) : (
            <Button variant="secondary" onClick={onClose}>
              {t('Yopish')}
            </Button>
          )
        }
      >
        <dl className="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-2 text-sm">
          {canViewAll && (
            <>
              <dt className="text-steel-600">{t('Oʻqituvchi')}</dt>
              <dd className="text-ink-900"><StaffName name={item.teacher.name} /></dd>
            </>
          )}
          <dt className="text-steel-600">{t('Mavzu')}</dt>
          <dd className="text-ink-900">{item.topic || t('Guruh nomi')}</dd>
          <dt className="text-steel-600">{t('Holat')}</dt>
          <dd>
            {item.skipped ? (
              <Badge tone="amber">{t('Bu kun bekor qilingan')}</Badge>
            ) : (
              <span className="text-ink-900">
                {t('Zoom uchrashuvi {date}, soat {time} da yaratiladi va Telegram’ga yuboriladi.', { date: formatDayMonth(day), time: formatTime(createdAt) })}
              </span>
            )}
          </dd>
          {item.error && (
            <>
              <dt className="text-steel-600">{t('Xato')}</dt>
              <dd className="text-onair-600">{item.error}</dd>
            </>
          )}
        </dl>
      </Modal>
      <ConfirmDialog
        open={confirmStop}
        title={t('Haftalik darsni toʻxtatasizmi?')}
        description={t('Keyingi haftalarda bu dars avtomatik yaratilmaydi. Oʻtgan darslar va hisobotlar saqlanadi.')}
        confirmLabel={t('Toʻxtatish')}
        tone="danger"
        loading={actions.cancel.isPending}
        onClose={() => setConfirmStop(false)}
        onConfirm={() =>
          actions.cancel.mutate(item.series_id, {
            onSuccess: () => {
              setConfirmStop(false)
              onClose()
            },
          })
        }
      />
    </>
  )
}

/** Haftalik darslar ro'yxati (kalendar ostida). */
export function SeriesList({ params, onAdd }: { params: Record<string, string>; onAdd?: () => void }) {
  const t = useT()
  const { canViewAll } = useAuth()
  const actions = useSeriesActions()
  const [stopping, setStopping] = useState<LessonSeries | null>(null)
  const [deleting, setDeleting] = useState<LessonSeries | null>(null)
  const series = useQuery({
    queryKey: ['series', params],
    queryFn: async () => (await api.get<{ data: LessonSeries[] }>('/api/series', { params })).data.data,
  })

  return (
    <Panel
      padded={false}
      title={
        <span className="flex items-center gap-2">
          <Repeat className="size-4 text-steel-500" />
          {t('Haftalik darslar')}
        </span>
      }
      actions={
        onAdd && (
          <Button size="sm" variant="secondary" onClick={onAdd}>
            {t('Qoʻshish')}
          </Button>
        )
      }
    >
      {!series.data?.length ? (
        <EmptyState
          title={t('Haftalik darslar yoʻq')}
          description={onAdd ? t('Har hafta bir xil vaqtda oʻtadigan darsni qoʻshing — tizim uni har hafta oʻzi yaratadi.') : t('Hozircha haftalik jadval qoʻshilmagan.')}
        />
      ) : (
        <ul className="divide-y divide-steel-100">
          {series.data.map((item) => (
            <li key={item.id} className="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-5">
              <div className="w-40 shrink-0">
                <p className="font-medium text-ink-900">{capital(weekdayName(item.weekday))}</p>
                <p className="text-sm text-steel-600 tabular-nums">
                  {item.slot.name}, {item.slot.starts_at}–{item.slot.ends_at}
                </p>
              </div>
              <div className="min-w-0 flex-1 text-sm">
                <p className="font-medium text-ink-900">{item.group.name}</p>
                <p className="text-steel-600">
                  {canViewAll && (
                    <>
                      <StaffName name={item.teacher.name} />.{' '}
                    </>
                  )}
                  {item.host ? t('Host: {email}', { email: item.host.email }) : t('Host avtomatik tanlanadi')}
                </p>
                {item.last_error && (
                  <p className="mt-1 flex items-start gap-1.5 text-onair-600">
                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                    {t('Bugun yaratilmadi: {reason}', { reason: item.last_error })}
                  </p>
                )}
              </div>
              <div className="flex items-center justify-between gap-3 text-sm sm:justify-end">
                <span className="text-steel-600">
                  {item.next_date ? t('Keyingisi: {date}', { date: formatDayMonth(dayDate(item.next_date)) }) : t('Yakunlangan')}
                  {item.ends_on && <span className="block text-xs text-steel-500">{t('{date} gacha', { date: formatDayMonth(dayDate(item.ends_on)) })}</span>}
                </span>
                <ActionMenu
                  items={[
                    { label: t('Jadvalni toʻxtatish'), icon: <CalendarOff className="size-4" />, onClick: () => setStopping(item), hidden: !item.can.cancel },
                    { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(item), hidden: !item.can.delete },
                  ]}
                />
              </div>
            </li>
          ))}
        </ul>
      )}
      <ConfirmDialog
        open={!!stopping}
        title={t('Haftalik darsni toʻxtatasizmi?')}
        description={t('Keyingi haftalarda bu dars avtomatik yaratilmaydi. Oʻtgan darslar va hisobotlar saqlanadi.')}
        confirmLabel={t('Toʻxtatish')}
        tone="danger"
        loading={actions.cancel.isPending}
        onClose={() => setStopping(null)}
        onConfirm={() => stopping && actions.cancel.mutate(stopping.id, { onSuccess: () => setStopping(null) })}
      />
      <ConfirmDialog
        open={!!deleting}
        title={t('Haftalik darsni oʻchirasizmi?')}
        description={t('Jadval butunlay oʻchiriladi. Undan yaratilgan darslar va hisobotlar saqlanadi.')}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={actions.remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && actions.remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })}
      />
    </Panel>
  )
}
