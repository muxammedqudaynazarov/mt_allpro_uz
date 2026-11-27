import { useMemo, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, ArrowLeft, CheckCircle2, ChevronRight, Combine, Eye, EyeOff, Pencil, RefreshCw, Search, UsersRound } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors, isStatus } from '@/lib/api'
import { addDays, formatDayMonth, formatDuration, isoDate } from '@/lib/format'
import { upperName, upperNameInput } from '@/lib/name'
import type { GroupStudentRow, GroupStudents } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'
import NotFoundPage from './NotFoundPage'

function RenameModal({ student, onClose }: { student: GroupStudentRow; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const [name, setName] = useState(upperName(student.name))
  const save = useMutation({
    mutationFn: () => api.patch(`/api/students/${student.id}`, { name: name.trim() }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['group-students'] })
      toast.success(t('Saqlandi'))
      onClose()
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
      title={t('Talaba nomi')}
      description={t('Hisobotlarda shu nom koʻrinadi. Zoom’dagi ismlar boʻyicha tanish oʻzgarmaydi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="rename-form" loading={save.isPending} disabled={!name.trim()}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      <form id="rename-form" onSubmit={submit}>
        <Input label={t('Familiya va ism')} value={name} maxLength={120} autoFocus onChange={upperNameInput(setName)} error={fieldErrors(save.error).name} required />
      </form>
    </Modal>
  )
}

function MergeModal({ groupId, students, onClose, onDone }: { groupId: number; students: GroupStudentRow[]; onClose: () => void; onDone: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const [targetId, setTargetId] = useState(() => [...students].sort((a, b) => b.lessons_attended - a.lessons_attended || b.name.length - a.name.length)[0].id)
  const merge = useMutation({
    mutationFn: () =>
      api.post(`/api/groups/${groupId}/students/merge`, {
        target_id: targetId,
        source_ids: students.map((student) => student.id).filter((id) => id !== targetId),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['group-students'] })
      queryClient.invalidateQueries({ queryKey: ['lesson-attendance'] })
      toast.success(t('Talabalar birlashtirildi'))
      onDone()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <Modal
      open
      onClose={onClose}
      title={t('{count} ta yozuvni birlashtirish', { count: students.length })}
      description={t('Bu yozuvlar bitta talabaga tegishli boʻlsa, birlashtiring. Uning barcha darslari qayta hisoblanadi, keyingi darslarda bu ismlar avtomatik tanib olinadi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button icon={<Combine className="size-4" />} loading={merge.isPending} onClick={() => merge.mutate()}>
            {t('Birlashtirish')}
          </Button>
        </>
      }
    >
      <fieldset>
        <legend className="mb-2 text-sm font-medium text-ink-800">{t('Qaysi nom qolsin?')}</legend>
        <div className="space-y-2">
          {students.map((student) => (
            <label
              key={student.id}
              className={cn(
                'flex cursor-pointer items-start gap-3 rounded-field p-3 ring-1 ring-inset',
                targetId === student.id ? 'bg-azure-50 ring-azure-300' : 'ring-steel-200 hover:bg-steel-50',
              )}
            >
              <input type="radio" name="target" className="mt-1 accent-azure-600" checked={targetId === student.id} onChange={() => setTargetId(student.id)} />
              <span className="min-w-0">
                <span className="block font-medium text-ink-900">{student.name}</span>
                <span className="block truncate text-sm text-steel-600">
                  {t('{count} ta dars', { count: student.lessons_attended })}
                  {student.names.length > 1 && `, ${student.names.filter((name) => name !== student.name).join(', ')}`}
                </span>
              </span>
            </label>
          ))}
        </div>
      </fieldset>
    </Modal>
  )
}

/** Talabaning HEMIS ID raqami: maydondan chiqqanda (yoki Enter) saqlanadi. */
function HemisIdField({ row }: { row: GroupStudentRow }) {
  const t = useT()
  const queryClient = useQueryClient()
  const [value, setValue] = useState(row.hemis_id ?? '')
  const save = useMutation({
    mutationFn: (hemisId: string) => api.patch(`/api/students/${row.id}`, { hemis_id: hemisId || null }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['group-students'] }),
    onError: (error) => {
      toast.error(fieldErrors(error).hemis_id ?? errorMessage(error))
      setValue(row.hemis_id ?? '')
    },
  })
  const commit = () => {
    const next = value.trim()
    if (next !== (row.hemis_id ?? '')) save.mutate(next)
  }
  const status =
    row.hemis_status === 'synced' ? (
      <CheckCircle2 className="size-4 shrink-0 text-leaf-500" aria-label={t('HEMIS’dan olingan')} />
    ) : row.hemis_status === 'not_found' ? (
      <AlertTriangle className="size-4 shrink-0 text-amber-600" aria-label={row.hemis_error ?? t('HEMIS’da topilmadi')} />
    ) : row.hemis_status === 'pending' ? (
      <span className="size-2 shrink-0 rounded-full bg-azure-500" aria-label={t('Sinxronlash kutilmoqda')} />
    ) : (
      <span className="size-4 shrink-0" />
    )
  return (
    <div className="flex items-center gap-2" title={row.hemis_status === 'not_found' ? (row.hemis_error ?? undefined) : undefined}>
      <input
        value={value}
        inputMode="numeric"
        maxLength={32}
        placeholder={t('HEMIS ID')}
        aria-label={t('{name}: HEMIS ID', { name: row.name })}
        disabled={save.isPending}
        onChange={(e) => setValue(e.target.value)}
        onBlur={commit}
        onKeyDown={(e) => {
          if (e.key === 'Enter') e.currentTarget.blur()
        }}
        className="h-9 w-36 rounded-[0.5rem] bg-white px-2.5 text-sm tabular-nums ring-1 ring-steel-200 ring-inset placeholder:text-steel-400 focus:ring-2 focus:ring-azure-500 focus:outline-none disabled:bg-steel-50"
      />
      {status}
    </div>
  )
}

export default function GroupStudentsPage() {
  const t = useT()
  const { id } = useParams()
  const queryClient = useQueryClient()
  const [from, setFrom] = useState(() => isoDate(addDays(new Date(), -29)))
  const [to, setTo] = useState(() => isoDate())
  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState<number[]>([])
  const [renaming, setRenaming] = useState<GroupStudentRow | null>(null)
  const [merging, setMerging] = useState(false)

  const query = useQuery({
    queryKey: ['group-students', id, from, to],
    queryFn: async () =>
      (
        await api.get<{ data: GroupStudents }>(`/api/groups/${id}/students`, {
          params: { from, to },
        })
      ).data.data,
    enabled: !!from && !!to,
  })

  const hemisSync = useMutation({
    mutationFn: async () => (await api.post<{ data: { synced: number; not_found: number; failed: number } }>(`/api/groups/${id}/students/hemis-sync`)).data.data,
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['group-students'] })
      toast.success(t('HEMIS: {synced} ta yangilandi, {not_found} ta topilmadi, {failed} ta xato', result))
    },
    onError: (error) => toast.error(fieldErrors(error).hemis ?? errorMessage(error)),
  })

  const toggleIgnored = useMutation({
    mutationFn: (student: GroupStudentRow) => api.patch(`/api/students/${student.id}`, { ignored: !student.ignored }),
    onSuccess: (_, student) => {
      queryClient.invalidateQueries({ queryKey: ['group-students'] })
      setSelected((current) => current.filter((value) => value !== student.id))
      toast.success(student.ignored ? t('{name} yana talaba sifatida hisoblanadi', { name: student.name }) : t('{name} roʻyxatdan olindi — “Talaba emas” boʻlimida', { name: student.name }))
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const data = query.data
  const matches = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase('uz')
    return (data?.students ?? []).filter((row) => !needle || [row.name, ...row.names].some((name) => name.toLocaleLowerCase('uz').includes(needle)))
  }, [data?.students, search])
  // "Talaba emas" deb belgilanganlar umumiy ro'yxatda emas — pastdagi alohida bo'limda.
  const rows = matches.filter((row) => !row.ignored)
  const ignoredRows = matches.filter((row) => row.ignored)
  const selectedRows = (data?.students ?? []).filter((row) => selected.includes(row.id))

  if (isStatus(query.error, 404) || isStatus(query.error, 403)) return <NotFoundPage />

  const presets = [
    { label: t('Oxirgi 7 kun'), from: isoDate(addDays(new Date(), -6)) },
    { label: t('Oxirgi 30 kun'), from: isoDate(addDays(new Date(), -29)) },
    { label: t('Oxirgi 90 kun'), from: isoDate(addDays(new Date(), -89)) },
  ]

  function toggle(rowId: number) {
    setSelected((current) => (current.includes(rowId) ? current.filter((value) => value !== rowId) : [...current, rowId]))
  }

  return (
    <>
      <Link to="/groups" className="mb-4 inline-flex items-center gap-1.5 text-sm text-steel-600 hover:text-ink-900">
        <ArrowLeft className="size-4" />
        {t('Guruhlar')}
      </Link>
      <PageHeader
        title={data ? t('{group}: talabalar', { group: data.group.name }) : t('Talabalar')}
        description={t('Talabalar Zoom’dagi ismi boʻyicha avtomatik aniqlanadi. Bir talaba bir nechta qatorda chiqsa, ularni belgilab birlashtiring — tizim buni eslab qoladi.')}
      />

      <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end">
        <Input type="date" label={t('Sanadan')} wrapperClassName="lg:w-44" value={from} max={to} onChange={(e) => setFrom(e.target.value)} />
        <Input type="date" label={t('Sanagacha')} wrapperClassName="lg:w-44" value={to} min={from} onChange={(e) => setTo(e.target.value)} />
        <div className="flex flex-wrap gap-2 lg:pb-0.5">
          {presets.map((preset) => (
            <button
              key={preset.label}
              type="button"
              onClick={() => {
                setFrom(preset.from)
                setTo(isoDate())
              }}
              className="h-10 rounded-full px-3.5 text-sm text-steel-700 ring-1 ring-steel-200 ring-inset hover:bg-white hover:text-ink-900"
            >
              {preset.label}
            </button>
          ))}
        </div>
        <div className="relative lg:ml-auto lg:w-64">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-steel-500" aria-hidden />
          <input
            type="search"
            aria-label={t('Ism boʻyicha qidirish')}
            placeholder={t('Ism boʻyicha qidirish')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-10 w-full rounded-field bg-white pr-3 pl-9 text-sm ring-1 ring-steel-200 ring-inset placeholder:text-steel-400 focus:ring-2 focus:ring-azure-500"
          />
        </div>
      </div>

      <Panel
        padded={false}
        title={
          data
            ? t('Davrda oʻtkazilgan darslar: {count}', {
                count: data.lessons_held,
              })
            : undefined
        }
      >
        {query.isPending ? (
          <SectionLoader />
        ) : !data?.students.length ? (
          <EmptyState
            icon={<UsersRound className="size-5" />}
            title={t('Hali talabalar yoʻq')}
            description={t('Talabalar birinchi Zoom darsidan keyin, hisobot olinganda shu yerda paydo boʻladi.')}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[56rem] text-sm">
              <thead className="border-b border-steel-100 text-left text-xs font-medium text-steel-500">
                <tr>
                  <th className="w-10 py-3 pl-5">
                    <span className="sr-only">{t('Tanlash')}</span>
                  </th>
                  <th className="px-3 py-3 font-medium">{t('Talaba')}</th>
                  <th className="px-3 py-3 font-medium">{t('HEMIS ID')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Darslar')}</th>
                  <th className="w-48 px-3 py-3 font-medium">{t('Qatnashuv')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Oʻrtacha kechikish')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Oxirgi marta')}</th>
                  <th className="w-12 py-3 pr-5" />
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {rows.map((row) => {
                  const otherNames = row.names.filter((name) => name !== row.name)
                  return (
                    <tr key={row.id} className={cn(selected.includes(row.id) && 'bg-azure-50/60')}>
                      <td className="py-3 pl-5 align-top">
                        <input
                          type="checkbox"
                          className="mt-1 size-4 accent-azure-600"
                          checked={selected.includes(row.id)}
                          onChange={() => toggle(row.id)}
                          aria-label={t('{name} ni tanlash', {
                            name: row.name,
                          })}
                        />
                      </td>
                      <td className="max-w-80 px-3 py-3">
                        <div className="flex items-center gap-3">
                          {row.picture_url && <Avatar name={row.name} src={row.picture_url} className="size-8" />}
                          <div className="min-w-0">
                            <p className="flex items-center gap-2 font-medium text-ink-900">
                              <span className="truncate">{row.name}</span>
                            </p>
                            {otherNames.length > 0 && (
                              <p className="truncate text-steel-500" title={otherNames.join(', ')}>
                                {t('Zoom’da: {names}', {
                                  names: otherNames.join(', '),
                                })}
                              </p>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-3 py-2">
                        <HemisIdField key={`${row.id}-${row.hemis_id ?? ''}`} row={row} />
                      </td>
                      <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">
                        <span className="font-semibold text-ink-900">{row.lessons_attended}</span>
                        <span className="text-steel-500"> / {data.lessons_held}</span>
                      </td>
                      <td className="px-3 py-3">
                        <div className="flex items-center gap-3">
                          <div className="h-1.5 flex-1 rounded-full bg-steel-100" aria-hidden>
                            <div className={cn('h-full rounded-full', (row.percent ?? 0) < 50 ? 'bg-amber-500' : 'bg-azure-500')} style={{ width: `${row.percent ?? 0}%` }} />
                          </div>
                          <span className="w-10 text-right tabular-nums">{row.percent !== null ? `${row.percent}%` : '—'}</span>
                        </div>
                        <p className="mt-1 text-xs text-steel-500">{formatDuration(row.minutes)}</p>
                      </td>
                      <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">{row.avg_late_minutes !== null ? formatDuration(row.avg_late_minutes) : '—'}</td>
                      <td className="px-3 py-3 text-right whitespace-nowrap text-steel-600">{row.last_seen_at ? formatDayMonth(row.last_seen_at) : '—'}</td>
                      <td className="py-2 pr-5 text-right">
                        <ActionMenu
                          items={[
                            {
                              label: t('Nomini oʻzgartirish'),
                              icon: <Pencil className="size-4" />,
                              onClick: () => setRenaming(row),
                            },
                            {
                              label: t('Talaba emas (hisobga olinmasin)'),
                              icon: <EyeOff className="size-4" />,
                              onClick: () => toggleIgnored.mutate(row),
                            },
                          ]}
                        />
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            {rows.length === 0 && (
              <p className="px-5 py-8 text-center text-sm text-steel-600">
                {search.trim() ? t('Qidiruv boʻyicha talaba topilmadi.') : t('Barcha qatnashchilar “Talaba emas” deb belgilangan.')}
              </p>
            )}
          </div>
        )}
        {ignoredRows.length > 0 && (
          <details className="group border-t border-steel-100">
            <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm text-steel-600 select-none hover:text-ink-900 [&::-webkit-details-marker]:hidden">
              <ChevronRight className="size-4 transition-transform group-open:rotate-90" aria-hidden />
              {t('Talaba emas deb belgilanganlar: {count}', { count: ignoredRows.length })}
              <span className="text-steel-500">— {t('davomat va hisobotlarda hisobga olinmaydi')}</span>
            </summary>
            <ul className="divide-y divide-steel-100 border-t border-steel-100 bg-mist/40">
              {ignoredRows.map((row) => {
                const otherNames = row.names.filter((name) => name !== row.name)
                return (
                  <li key={row.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5 pr-5 pl-14 text-sm">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-steel-700">{row.name}</p>
                      <p className="truncate text-xs text-steel-500" title={otherNames.join(', ')}>
                        {t('{count} ta darsda', { count: row.lessons_attended })}
                        {row.last_seen_at && `, ${t('oxirgi marta {date}', { date: formatDayMonth(row.last_seen_at) })}`}
                        {otherNames.length > 0 && `. ${t('Zoom’da: {names}', { names: otherNames.join(', ') })}`}
                      </p>
                    </div>
                    <Button size="sm" variant="secondary" icon={<Eye className="size-4" />} loading={toggleIgnored.isPending && toggleIgnored.variables?.id === row.id} onClick={() => toggleIgnored.mutate(row)}>
                      {t('Talaba sifatida hisoblash')}
                    </Button>
                  </li>
                )
              })}
            </ul>
          </details>
        )}
        {data && data.students.length > 0 && (
          <div className="flex flex-col gap-3 border-t border-steel-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-steel-600">
              {!data.hemis.enabled
                ? t('HEMIS_API_TOKEN .env faylida sozlanmagan — HEMIS’dan maʼlumot olib boʻlmaydi.')
                : data.hemis.pending
                  ? t('{count} ta talabaning HEMIS ID si yangi yoki oʻzgargan. Haqiqiy F.I.Sh. va rasm faqat ular uchun yuklanadi.', { count: data.hemis.pending })
                  : t('Talabaga HEMIS ID kiriting — HEMIS SYNC haqiqiy F.I.Sh. va rasmni yuklaydi. Avval olinganlar qayta soʻralmaydi.')}
            </p>
            <Button icon={<RefreshCw className="size-4" />} disabled={!data.hemis.enabled || !data.hemis.pending} loading={hemisSync.isPending} onClick={() => hemisSync.mutate()}>
              HEMIS SYNC
            </Button>
          </div>
        )}
      </Panel>

      {selectedRows.length > 0 && (
        <div className="sticky bottom-4 z-30 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-panel bg-ink-900 px-5 py-3 text-white shadow-[0_16px_40px_-12px_rgb(12_27_51/0.5)]">
          <p className="text-sm">{t('{count} ta tanlandi', { count: selectedRows.length })}</p>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" className="text-ink-100 hover:bg-white/10" onClick={() => setSelected([])}>
              {t('Bekor qilish')}
            </Button>
            <Button size="sm" icon={<Combine className="size-4" />} disabled={selectedRows.length < 2} onClick={() => setMerging(true)}>
              {t('Birlashtirish')}
            </Button>
          </div>
        </div>
      )}

      {renaming && <RenameModal student={renaming} onClose={() => setRenaming(null)} />}
      {merging && data && selectedRows.length > 1 && (
        <MergeModal
          groupId={data.group.id}
          students={selectedRows}
          onClose={() => setMerging(false)}
          onDone={() => {
            setMerging(false)
            setSelected([])
          }}
        />
      )}
    </>
  )
}
