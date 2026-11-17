import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Archive, CalendarCheck, CalendarRange, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { dayDate, formatDate } from '@/lib/format'
import type { AcademicYear, AcademicYearStatus } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

const STATUS_LABELS: Record<AcademicYearStatus, string> = { planned: 'Rejada', active: 'Joriy', archived: 'Arxiv' }
const STATUS_TONE: Record<AcademicYearStatus, 'azure' | 'leaf' | 'neutral'> = { planned: 'azure', active: 'leaf', archived: 'neutral' }

interface YearForm {
  name: string
  status: AcademicYearStatus
  autumn: { starts_on: string; ends_on: string }
  spring: { starts_on: string; ends_on: string }
}

/** Yangi o'quv yili uchun taklif: oxirgi yildan keyingisi, odatiy sanalar bilan. */
function suggestion(years: AcademicYear[]): YearForm {
  const last = years.map((year) => Number(year.name.slice(0, 4))).filter(Boolean)
  const thisYear = new Date().getFullYear()
  const first = last.length ? Math.max(...last) + 1 : new Date().getMonth() >= 7 ? thisYear : thisYear - 1
  return {
    name: `${first}–${first + 1}`,
    status: 'planned',
    autumn: { starts_on: `${first}-09-01`, ends_on: `${first + 1}-01-20` },
    spring: { starts_on: `${first + 1}-02-01`, ends_on: `${first + 1}-06-30` },
  }
}

function YearModal({ year, years, onClose }: { year: AcademicYear | 'new'; years: AcademicYear[]; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const editing = year !== 'new'
  const [form, setForm] = useState<YearForm>(() => {
    if (!editing) return suggestion(years)
    const autumn = year.semesters.find((semester) => semester.type === 'autumn')
    const spring = year.semesters.find((semester) => semester.type === 'spring')
    return {
      name: year.name,
      status: year.status,
      autumn: { starts_on: autumn?.starts_on ?? '', ends_on: autumn?.ends_on ?? '' },
      spring: { starts_on: spring?.starts_on ?? '', ends_on: spring?.ends_on ?? '' },
    }
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: async () => (editing ? api.put(`/api/academic-years/${year.id}`, form) : api.post('/api/academic-years', form)),
    onSuccess: () => {
      toast.success(t('Oʻquv yili saqlandi'))
      queryClient.invalidateQueries({ queryKey: ['academic-years'] })
      queryClient.invalidateQueries({ queryKey: ['semesters'] })
      onClose()
    },
    onError: (error) => {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) toast.error(errorMessage(error))
    },
  })
  const setDate = (type: 'autumn' | 'spring', key: 'starts_on' | 'ends_on', value: string) => setForm({ ...form, [type]: { ...form[type], [key]: value } })

  function submit(event: FormEvent) {
    event.preventDefault()
    setErrors({})
    save.mutate()
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={editing ? t('Oʻquv yilini tahrirlash') : t('Oʻquv yili qoʻshish')}
      description={t('Har bir oʻquv yilida ikkita semestr. Darslar sanasiga qarab semestrga avtomatik biriktiriladi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="year-form" loading={save.isPending}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      <form id="year-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <Input label={t('Oʻquv yili')} placeholder="2026–2027" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} required />
        <Select label={t('Holat')} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as AcademicYearStatus })} error={errors.status}>
          {(Object.keys(STATUS_LABELS) as AcademicYearStatus[]).map((status) => (
            <option key={status} value={status}>
              {t(STATUS_LABELS[status])}
            </option>
          ))}
        </Select>
        {(['autumn', 'spring'] as const).map((type) => (
          <fieldset key={type} className="rounded-field p-4 ring-1 ring-steel-200 ring-inset sm:col-span-2">
            <legend className="px-1 text-sm font-medium text-ink-900">{type === 'autumn' ? t('Kuzgi semestr') : t('Bahorgi semestr')}</legend>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label={t('Boshlanish sanasi')}
                type="date"
                value={form[type].starts_on}
                onChange={(e) => setDate(type, 'starts_on', e.target.value)}
                error={errors[`${type}.starts_on`]}
                required
              />
              <Input
                label={t('Tugash sanasi')}
                type="date"
                min={form[type].starts_on}
                value={form[type].ends_on}
                onChange={(e) => setDate(type, 'ends_on', e.target.value)}
                error={errors[`${type}.ends_on`]}
                required
              />
            </div>
          </fieldset>
        ))}
        {form.status === 'active' && <p className="text-sm text-steel-600 sm:col-span-2">{t('Joriy oʻquv yili bitta boʻladi: avvalgi joriy yil arxivga oʻtadi.')}</p>}
      </form>
    </Modal>
  )
}

export default function AcademicYearsPage() {
  const t = useT()
  const { isSuperAdmin } = useAuth()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<AcademicYear | 'new' | null>(null)
  const [deleting, setDeleting] = useState<AcademicYear | null>(null)
  const years = useQuery({
    queryKey: ['academic-years'],
    queryFn: async () => (await api.get<{ data: AcademicYear[] }>('/api/academic-years')).data.data,
  })
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['academic-years'] })
    queryClient.invalidateQueries({ queryKey: ['semesters'] })
  }
  const setStatus = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: AcademicYearStatus }) => api.patch(`/api/academic-years/${id}/status`, { status }),
    onSuccess: () => {
      toast.success(t('Holat oʻzgartirildi'))
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/academic-years/${id}`),
    onSuccess: () => {
      toast.success(t('Oʻquv yili oʻchirildi'))
      setDeleting(null)
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <PageHeader
        title={t('Oʻquv yillari')}
        description={
          isSuperAdmin
            ? t('Har bir oʻquv yilida Kuzgi va Bahorgi semestr. Darslar semestrga biriktirilib arxivlanadi.')
            : t('Oʻquv yillari va semestrlar. Oʻzgartirish — Administrator vakolatida.')
        }
        actions={
          isSuperAdmin && (
            <Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
              {t('Oʻquv yili qoʻshish')}
            </Button>
          )
        }
      />
      {years.isPending ? (
        <SectionLoader />
      ) : !years.data?.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          icon={<CalendarRange className="size-5" />}
          title={t('Oʻquv yillari qoʻshilmagan')}
          description={isSuperAdmin ? t('Oʻquv yilini va uning ikki semestri sanalarini kiriting.') : t('Administrator oʻquv yilini qoʻshishi kerak.')}
          action={isSuperAdmin ? <Button onClick={() => setEditing('new')}>{t('Oʻquv yili qoʻshish')}</Button> : undefined}
        />
      ) : (
        <ul className="space-y-4">
          {years.data.map((year) => (
            <li key={year.id} className={cn('rounded-panel bg-white ring-1 ring-steel-200/70', year.status === 'archived' && 'bg-white/60')}>
              <div className="flex items-center justify-between gap-3 border-b border-steel-100 px-5 py-4">
                <div className="flex items-center gap-3">
                  <h2 className="font-display text-lg font-semibold text-ink-900 tabular-nums">{year.name}</h2>
                  <Badge tone={STATUS_TONE[year.status]}>{t(STATUS_LABELS[year.status])}</Badge>
                </div>
                {isSuperAdmin && (
                  <ActionMenu
                    items={[
                      { label: t('Tahrirlash'), icon: <Pencil className="size-4" />, onClick: () => setEditing(year) },
                      {
                        label: t('Joriy qilish'),
                        icon: <CalendarCheck className="size-4" />,
                        onClick: () => setStatus.mutate({ id: year.id, status: 'active' }),
                        hidden: year.status === 'active',
                      },
                      {
                        label: t('Arxivlash'),
                        icon: <Archive className="size-4" />,
                        onClick: () => setStatus.mutate({ id: year.id, status: 'archived' }),
                        hidden: year.status === 'archived',
                      },
                      {
                        label: t('Rejaga qaytarish'),
                        icon: <CalendarRange className="size-4" />,
                        onClick: () => setStatus.mutate({ id: year.id, status: 'planned' }),
                        hidden: year.status === 'planned',
                      },
                      { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(year) },
                    ]}
                  />
                )}
              </div>
              <div className="grid divide-y divide-steel-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                {year.semesters.map((semester) => (
                  <div key={semester.id} className="px-5 py-4">
                    <p className="flex items-center gap-2 text-sm font-medium text-ink-900">
                      {semester.type === 'autumn' ? t('Kuzgi semestr') : t('Bahorgi semestr')}
                      {semester.current && <Badge tone="leaf">{t('Hozir')}</Badge>}
                    </p>
                    <p className="mt-0.5 text-sm text-steel-600 tabular-nums">
                      {formatDate(dayDate(semester.starts_on))} – {formatDate(dayDate(semester.ends_on))}
                    </p>
                    <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                      <Link to={`/lessons?semester_id=${semester.id}`} className="text-azure-600 hover:underline">
                        {t('{count} ta dars', { count: semester.lessons_count })}
                      </Link>
                      <Link to={`/reports?tab=semester&semester=${semester.id}`} className="text-azure-600 hover:underline">
                        {t('Semestr hisoboti')}
                      </Link>
                    </p>
                  </div>
                ))}
              </div>
            </li>
          ))}
        </ul>
      )}
      {editing && <YearModal year={editing} years={years.data ?? []} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title={t('Oʻquv yilini oʻchirasizmi?')}
        description={t('Faqat darslari yoʻq oʻquv yilini oʻchirish mumkin. Darslari bor yilni arxivlang.')}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}
