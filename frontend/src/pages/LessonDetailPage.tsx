import { Fragment, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Download, RefreshCw, UserRoundX, Users } from 'lucide-react'
import { toast } from 'sonner'
import { withBase } from '@/lib/base'
import { api, errorMessage, isStatus } from '@/lib/api'
import { formatDuration, formatLongToday, formatTime } from '@/lib/format'
import { groupsLabel } from '@/lib/groups'
import type { AttendanceRow, GroupRef, LessonAttendance, UnassignedParticipant } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { Button, buttonClass } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { LectureBadge } from '@/features/lessons/LectureBadge'
import { LessonActions } from '@/features/lessons/LessonActions'
import { LiveParticipantsPanel } from '@/features/lessons/LiveParticipants'
import { LessonRecordings } from '@/features/recordings/LessonRecordings'
import { LessonStatusBadge } from '@/features/lessons/LessonStatusBadge'
import { delayToneClass, LATE_START_MINUTES, PresenceStrip, startDelay } from '@/features/lessons/ZoomTracking'
import { useT } from '@/i18n/I18nProvider'
import NotFoundPage from './NotFoundPage'

type Sort = 'name' | 'minutes' | 'joined'

/** Vaqt o'qi ostidagi belgilar: boshlanish, o'rta va tugash. */
function Axis({ from, to }: { from: number; to: number }) {
  const middle = from + (to - from) / 2
  return (
    <div className="flex justify-between text-xs text-steel-500 tabular-nums" aria-hidden>
      <span>{formatTime(new Date(from))}</span>
      <span>{formatTime(new Date(middle))}</span>
      <span>{formatTime(new Date(to))}</span>
    </div>
  )
}

function StudentRow({ row, from, to, presence, muted = false }: { row: AttendanceRow; from: number; to: number; presence: number; muted?: boolean }) {
  const t = useT()
  const otherNames = row.names.filter((name) => name !== row.name)
  // Qatnashgan hisoblanish chegarasidan kam: darsda bo'lmagan hisoblanadi — xira matn, boshqa rangdagi chiziq.
  const below = !muted && row.percent !== null && row.percent < presence
  return (
    <li
      className={cn('grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_5.5rem_8.5rem]', muted && 'opacity-60')}
      title={below ? t('Qatnashgan hisoblanmaydi: {percent}% (chegara {presence}%)', { percent: row.percent ?? 0, presence }) : undefined}
    >
      <div className="min-w-0">
        <p className={cn('font-medium break-words md:truncate', below ? 'text-steel-500' : 'text-ink-900')} title={row.name}>
          {row.name}
        </p>
        {otherNames.length > 0 && (
          <p className="truncate text-sm text-steel-500" title={otherNames.join(', ')}>
            {t('Zoom’da: {names}', { names: otherNames.join(', ') })}
          </p>
        )}
      </div>
      <PresenceStrip
        from={from}
        to={to}
        segments={row.segments}
        tone={muted ? 'steel' : below ? 'amber' : 'azure'}
        label={t('{name} darsda boʻlgan vaqt', { name: row.name })}
        className="order-last col-span-2 md:order-none md:col-span-1"
      />
      <div className="text-right text-sm tabular-nums md:text-left">
        {/* Telefonda alohida ustun yo'q: qatnashgan vaqt shu yerda. */}
        <p className={cn('font-medium md:hidden', below ? 'text-steel-500' : 'text-ink-900')}>
          {formatDuration(row.minutes)}
          {row.percent !== null && <span className={cn('ml-1.5 font-normal', below ? 'text-amber-600' : 'text-steel-500')}>{row.percent}%</span>}
        </p>
        <p className={cn(below ? 'text-steel-500' : 'text-steel-600 md:text-ink-800')}>
          {row.first_joined_at ? formatTime(row.first_joined_at) : '—'}
          {row.late_minutes > 0 && (
            <span className={cn('ml-1.5 md:ml-0 md:block', row.late_minutes > LATE_START_MINUTES ? 'text-amber-600' : 'text-steel-500')}>+{formatDuration(row.late_minutes)}</span>
          )}
        </p>
      </div>
      <div className="hidden text-right text-sm md:block">
        <p className={cn('font-medium tabular-nums', below ? 'text-steel-500' : 'text-ink-900')}>{formatDuration(row.minutes)}</p>
        <p className="text-steel-500">
          {row.percent !== null && <span className={cn('tabular-nums', below && 'text-amber-600')}>{row.percent}%</span>}
          {row.sessions > 1 && <span className="ml-2">{t('{count} marta kirgan', { count: row.sessions })}</span>}
        </p>
      </div>
    </li>
  )
}

/** Ma'ruza: guruhi aniqlanmagan qatnashchi — o'qituvchi guruhini tanlaydi. */
function UnassignedRow({
  row,
  from,
  to,
  groups,
  pending,
  onAssign,
}: {
  row: UnassignedParticipant
  from: number
  to: number
  groups: GroupRef[]
  pending: boolean
  onAssign: (groupId: number) => void
}) {
  const t = useT()
  const otherNames = row.names.filter((name) => name !== row.name)
  return (
    <li className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_5.5rem_8.5rem]">
      <div className="min-w-0">
        <p className="font-medium break-words text-ink-900 md:truncate" title={row.name}>
          {row.name}
        </p>
        <p className="truncate text-sm text-steel-500" title={otherNames.join(', ')}>
          {formatDuration(row.minutes)}
          {row.percent !== null && `, ${row.percent}%`}
          {otherNames.length > 0 && `. ${t('Zoom’da: {names}', { names: otherNames.join(', ') })}`}
        </p>
      </div>
      <PresenceStrip
        from={from}
        to={to}
        segments={row.segments}
        tone="steel"
        label={t('{name} darsda boʻlgan vaqt', { name: row.name })}
        className="order-last col-span-2 md:order-none md:col-span-1"
      />
      <p className="hidden text-sm text-steel-600 tabular-nums md:block">{row.first_joined_at ? formatTime(row.first_joined_at) : '—'}</p>
      <select
        value=""
        disabled={pending}
        onChange={(event) => event.target.value && onAssign(Number(event.target.value))}
        aria-label={t('{name} qaysi guruh talabasi', { name: row.name })}
        className="h-9 w-36 min-w-0 rounded-field bg-white px-2.5 text-sm md:w-full text-ink-800 ring-1 ring-steel-200 ring-inset focus-visible:ring-2 focus-visible:ring-azure-500 focus-visible:outline-none disabled:opacity-60"
      >
        <option value="">{t('Guruhini tanlang')}</option>
        {groups.map((group) => (
          <option key={group.id} value={group.id}>
            {group.name}
          </option>
        ))}
      </select>
    </li>
  )
}

/** Ma'ruzada talabalar guruhlar bo'yicha: guruh sarlavhasi va uning talabalari. */
function GroupHeading({ group, count, link }: { group: GroupRef; count: number; link: boolean }) {
  const t = useT()
  return (
    <li className="flex items-center justify-between gap-3 bg-mist/70 px-5 py-2 text-sm">
      <span className="font-semibold text-ink-900">
        {group.name}
        <span className="ml-2 font-normal text-steel-600">{t('{count} talaba', { count })}</span>
      </span>
      {link && (
        <Link to={`/groups/${group.id}/students`} className="text-azure-600 hover:underline">
          {t('Guruh talabalari')}
        </Link>
      )}
    </li>
  )
}

export default function LessonDetailPage() {
  const t = useT()
  const { id } = useParams()
  const queryClient = useQueryClient()
  const [sort, setSort] = useState<Sort>('name')

  const query = useQuery({
    queryKey: ['lesson-attendance', id],
    queryFn: async () => (await api.get<{ data: LessonAttendance }>(`/api/lessons/${id}/attendance`)).data.data,
    // Efirdagi va hisoboti kutilayotgan darslar sahifa ochiq turganda yangilanib turadi.
    refetchInterval: (q) => {
      const tracking = q.state.data?.lesson.tracking
      return tracking && (tracking.state === 'started' || tracking.state === 'waiting' || (tracking.state === 'ended' && !tracking.synced)) ? 30_000 : false
    },
  })

  const assign = useMutation({
    mutationFn: async ({ key, group_id }: { key: string; group_id: number }) =>
      (await api.post<{ data: LessonAttendance }>(`/api/lessons/${id}/attendance/assign`, { key, group_id })).data.data,
    onSuccess: (data, variables) => {
      queryClient.setQueryData(['lesson-attendance', id], data)
      const group = data.groups.find((item) => item.id === variables.group_id)
      toast.success(t('{group} guruhiga biriktirildi', { group: group?.name ?? '' }))
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const sync = useMutation({
    mutationFn: async () => (await api.post<{ data: LessonAttendance }>(`/api/lessons/${id}/attendance/sync`)).data.data,
    onSuccess: (data) => {
      queryClient.setQueryData(['lesson-attendance', id], data)
      toast.success(t('Zoom hisoboti olindi'))
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const data = query.data
  const students = useMemo(() => {
    const rows = [...(data?.students ?? [])]
    if (sort === 'minutes') rows.sort((a, b) => b.minutes - a.minutes)
    if (sort === 'joined') rows.sort((a, b) => (a.first_joined_at ?? '').localeCompare(b.first_joined_at ?? ''))
    return rows
  }, [data?.students, sort])

  if (query.isPending) return <SectionLoader />
  if (isStatus(query.error, 404) || isStatus(query.error, 403)) return <NotFoundPage />
  if (!data) return <EmptyState title={t('Maʼlumotni yuklab boʻlmadi')} description={errorMessage(query.error)} />

  const { lesson, host } = data
  const tracking = lesson.tracking
  const delay = startDelay(t, tracking.start_delay_minutes)
  const counted = students.filter((row) => !row.ignored)
  const ignored = students.filter((row) => row.ignored)
  // Ma'ruza (bir nechta guruh): talabalar guruhlar bo'yicha bo'linadi.
  const byGroups = lesson.is_lecture && data.groups.length > 1
  const unassigned = data.unassigned ?? []
  const finished = lesson.status === 'finished' || tracking.state === 'ended' || tracking.state === 'missed'
  const canSync = finished && tracking.state !== 'started'

  // Umumiy vaqt o'qi: dars Zoom'da boshlangan va tugagan vaqt (bo'lmasa — jadval).
  const from = new Date(tracking.started_at ?? lesson.starts_at).getTime()
  const to = Math.max(from + 60_000, new Date(tracking.ended_at ?? lesson.ended_at ?? lesson.ends_at).getTime())

  const stats = [
    {
      label: t('Jadval boʻyicha'),
      value: `${formatTime(lesson.starts_at)}–${formatTime(lesson.ends_at)}`,
      note: lesson.slot?.name,
    },
    {
      label: t('Zoom’da boshlandi'),
      value: tracking.started_at ? formatTime(tracking.started_at) : '—',
      note: delay ? (
        <span className={delayToneClass[delay.tone]}>{delay.text}</span>
      ) : tracking.state === 'missed' ? (
        <span className="text-amber-600">{t('Oʻtkazilmagan')}</span>
      ) : null,
    },
    {
      label: t('Davom etdi'),
      value: tracking.minutes !== null ? formatDuration(tracking.minutes) : '—',
      note: tracking.ended_at ? t('{time} da tugadi', { time: formatTime(tracking.ended_at) }) : tracking.state === 'started' ? t('Hozir efirda') : null,
    },
    {
      label: t('Qatnashdi'),
      value: tracking.synced && tracking.state === 'ended' ? t('{count} talaba', { count: counted.length }) : '—',
      note:
        byGroups && unassigned.length > 0 ? (
          <span className="text-amber-600">{t('Yana {count} nafarining guruhi aniqlanmagan', { count: unassigned.length })}</span>
        ) : counted.length > 0 ? (
          t('Oʻrtacha {time}', {
            time: formatDuration(counted.reduce((sum, row) => sum + row.minutes, 0) / counted.length),
          })
        ) : null,
    },
  ]

  let notice: { text: string; tone: 'neutral' | 'amber' } | null = null
  if (tracking.state === 'missed')
    notice = {
      text: t('Host bu darsni Zoom’da boshlamagan. Talabalar uchun uchrashuv ochilmagan.'),
      tone: 'amber',
    }
  else if (tracking.state === 'started')
    notice = {
      text: t('Dars hozir Zoom’da davom etmoqda. Talabalar davomati dars tugagach, Zoom hisobotidan olinadi.'),
      tone: 'neutral',
    }
  else if (tracking.state === 'ended' && !tracking.synced)
    notice = {
      text: tracking.error ?? t('Zoom hisoboti tayyorlanmoqda. Odatda dars tugaganidan keyin bir necha daqiqada keladi.'),
      tone: tracking.error ? 'amber' : 'neutral',
    }
  else if (!finished)
    notice = {
      text: t('Dars Zoom’da hali boshlanmagan. Host boshlagach, bu yerda haqiqiy vaqt koʻrinadi.'),
      tone: 'neutral',
    }

  return (
    <>
      <Link to="/lessons" className="mb-4 inline-flex items-center gap-1.5 text-sm text-steel-600 hover:text-ink-900">
        <ArrowLeft className="size-4" />
        {t('Darslar')}
      </Link>
      <PageHeader
        title={
          <span className="flex flex-wrap items-center gap-3">
            {groupsLabel(lesson)}
            {lesson.is_lecture && <LectureBadge />}
            <LessonStatusBadge status={lesson.status} />
          </span>
        }
        description={
          <>
            <span className="block">
              {formatLongToday(lesson.starts_at)}, <StaffName name={lesson.teacher.name} />
            </span>
            {lesson.topic && <span className="block text-steel-500">{lesson.topic}</span>}
          </>
        }
        actions={
          <>
            {finished && (
              <a href={withBase(`/api/lessons/${lesson.id}/attendance/export`)} className={buttonClass({ variant: 'secondary' })}>
                <Download className="size-4" />
                {t('Excel (.xlsx)')}
              </a>
            )}
            {canSync && (
              <Button variant="secondary" icon={<RefreshCw className="size-4" />} loading={sync.isPending} onClick={() => sync.mutate()}>
                {t('Zoom hisobotini yangilash')}
              </Button>
            )}
            <LessonActions lesson={lesson} />
          </>
        }
      />

      <dl className="mb-6 grid grid-cols-2 overflow-hidden rounded-panel bg-white ring-1 ring-steel-200/70 lg:grid-cols-4">
        {stats.map((item, index) => (
          <div key={item.label} className={cn('border-steel-100 px-5 py-5', index % 2 && 'border-l', index > 1 && 'border-t lg:border-t-0', index === 2 && 'lg:border-l')}>
            <dt className="text-sm text-steel-600">{item.label}</dt>
            <dd className="mt-1 font-display text-xl font-semibold tabular-nums">{item.value}</dd>
            {item.note && <dd className="mt-0.5 text-sm text-steel-600">{item.note}</dd>}
          </div>
        ))}
      </dl>

      {notice && (
        <p className={cn('mb-6 rounded-field p-4 text-sm', notice.tone === 'amber' ? 'bg-amber-50 text-amber-600' : 'bg-white text-steel-700 ring-1 ring-steel-200/70')}>
          {notice.text}
        </p>
      )}

      {tracking.state === 'started' && <LiveParticipantsPanel lesson={lesson} className="mb-6" />}

      <LessonRecordings lesson={lesson} />

      {tracking.synced && tracking.state === 'ended' && (
        <Panel
          padded={false}
          title={t('Talabalar')}
          actions={
            <div className="flex items-center gap-3">
              {data.can_manage_students && !byGroups && (
                <Link to={`/groups/${lesson.group.id}/students`} className="text-sm text-azure-600 hover:underline">
                  {t('Guruh talabalari')}
                </Link>
              )}
            </div>
          }
        >
          {students.length === 0 && unassigned.length === 0 ? (
            <EmptyState icon={<UserRoundX className="size-5" />} title={t('Darsga talabalar kirmagan')} description={t('Zoom hisobotida hostdan boshqa qatnashchi yoʻq.')} />
          ) : (
            <>
              <div className="flex flex-col gap-3 border-b border-steel-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <Segmented<Sort>
                  label={t('Tartib')}
                  value={sort}
                  onChange={setSort}
                  options={[
                    { value: 'name', label: t('Ism') },
                    { value: 'minutes', label: t('Qatnashuv') },
                    { value: 'joined', label: t('Kirgan vaqti') },
                  ]}
                />
                <p className="text-sm text-steel-600">{t('Talaba Zoom’dagi ismi boʻyicha aniqlanadi.')}</p>
              </div>
              <div className="hidden grid-cols-[minmax(0,15rem)_minmax(0,1fr)_5.5rem_8.5rem] gap-x-4 px-5 pt-3 pb-1 text-xs font-medium text-steel-500 md:grid">
                <span>{t('Talaba')}</span>
                <Axis from={from} to={to} />
                <span>{t('Kirdi')}</span>
                <span className="text-right">{t('Qatnashdi')}</span>
              </div>
              <ul className="divide-y divide-steel-100">
                {host && (
                  <li className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 bg-mist/60 px-5 py-3 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_5.5rem_8.5rem]">
                    <div className="min-w-0">
                      <p className="font-medium break-words text-ink-900 md:truncate" title={lesson.teacher.name}>
                        <StaffName name={lesson.teacher.name} />
                      </p>
                      <p className="text-sm text-steel-500">{t('Oʻqituvchi (host)')}</p>
                    </div>
                    <PresenceStrip
                      from={from}
                      to={to}
                      segments={host.segments}
                      tone="ink"
                      label={t('Host darsda boʻlgan vaqt')}
                      className="order-last col-span-2 md:order-none md:col-span-1"
                    />
                    <p className="text-right text-sm text-ink-800 tabular-nums md:text-left">{host.first_joined_at ? formatTime(host.first_joined_at) : '—'}</p>
                    <span className="hidden md:block" />
                  </li>
                )}
                {byGroups
                  ? data.groups.map((group) => {
                      const rows = counted.filter((row) => row.group_id === group.id)
                      return (
                        <Fragment key={group.id}>
                          <GroupHeading group={group} count={rows.length} link={data.can_manage_students} />
                          {rows.map((row) => (
                            <StudentRow key={row.student_id} row={row} from={from} to={to} presence={data.presence_percent} />
                          ))}
                        </Fragment>
                      )
                    })
                  : counted.map((row) => <StudentRow key={row.student_id} row={row} from={from} to={to} presence={data.presence_percent} />)}
              </ul>
              {byGroups && unassigned.length > 0 && (
                <section className="border-t border-steel-100" aria-labelledby="unassigned-title">
                  <div className="bg-amber-50 px-5 py-3">
                    <h3 id="unassigned-title" className="text-sm font-semibold text-ink-900">
                      {t('Guruhi aniqlanmagan: {count}', { count: unassigned.length })}
                    </h3>
                    <p className="mt-0.5 text-sm text-steel-700">
                      {t('Zoom’dagi ismi hech bir guruh talabasiga mos kelmadi. Guruhini tanlang — keyingi darslarda avtomatik taniladi.')}
                    </p>
                  </div>
                  <ul className="divide-y divide-steel-100">
                    {unassigned.map((row) => (
                      <UnassignedRow
                        key={row.key}
                        row={row}
                        from={from}
                        to={to}
                        groups={data.groups}
                        pending={assign.isPending && assign.variables?.key === row.key}
                        onAssign={(groupId) => assign.mutate({ key: row.key, group_id: groupId })}
                      />
                    ))}
                  </ul>
                </section>
              )}
              {ignored.length > 0 && (
                <details className="border-t border-steel-100">
                  <summary className="cursor-pointer px-5 py-3 text-sm text-steel-600 hover:text-ink-900">
                    {t('Hisobga olinmaganlar: {count}', {
                      count: ignored.length,
                    })}
                  </summary>
                  <ul className="divide-y divide-steel-100">
                    {ignored.map((row) => (
                      <StudentRow key={row.student_id} row={row} from={from} to={to} presence={data.presence_percent} muted />
                    ))}
                  </ul>
                </details>
              )}
            </>
          )}
        </Panel>
      )}

      {data.can_manage_students && !byGroups && !(tracking.synced && tracking.state === 'ended') && (
        <Link to={`/groups/${lesson.group.id}/students`} className={cn(buttonClass({ variant: 'quiet' }), 'mt-2')}>
          <Users className="size-4" />
          {t('Guruh talabalari')}
        </Link>
      )}
      {tracking.synced && tracking.state === 'ended' && students.length > 0 && (
        <p className="mt-3 text-sm text-steel-500">{t('Bir talaba bir nechta qatorda chiqsa, administrator “Guruh talabalari” sahifasida ularni birlashtiradi.')}</p>
      )}
    </>
  )
}
