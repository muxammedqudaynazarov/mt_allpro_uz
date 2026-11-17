import { useMemo, useState } from 'react'
import { Link, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Download, RefreshCw, UserRoundX, Users } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, isStatus } from '@/lib/api'
import { formatDuration, formatLongToday, formatTime } from '@/lib/format'
import type { AttendanceRow, LessonAttendance } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { Button, buttonClass } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
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

function StudentRow({ row, from, to, muted = false }: { row: AttendanceRow; from: number; to: number; muted?: boolean }) {
  const t = useT()
  const otherNames = row.names.filter((name) => name !== row.name)
  return (
    <li
      className={cn('grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_5.5rem_8.5rem]', muted && 'opacity-60')}
    >
      <div className="min-w-0">
        <p className="truncate font-medium text-ink-900">{row.name}</p>
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
        tone={muted ? 'steel' : 'azure'}
        label={t('{name} darsda boʻlgan vaqt', { name: row.name })}
        className="order-last col-span-2 md:order-none md:col-span-1"
      />
      <div className="text-right text-sm tabular-nums md:text-left">
        {/* Telefonda alohida ustun yo'q: qatnashgan vaqt shu yerda. */}
        <p className="font-medium text-ink-900 md:hidden">
          {formatDuration(row.minutes)}
          {row.percent !== null && <span className={cn('ml-1.5 font-normal', row.percent < 50 ? 'text-amber-600' : 'text-steel-500')}>{row.percent}%</span>}
        </p>
        <p className="text-steel-600 md:text-ink-800">
          {row.first_joined_at ? formatTime(row.first_joined_at) : '—'}
          {row.late_minutes > 0 && (
            <span className={cn('ml-1.5 md:ml-0 md:block', row.late_minutes > LATE_START_MINUTES ? 'text-amber-600' : 'text-steel-500')}>+{formatDuration(row.late_minutes)}</span>
          )}
        </p>
      </div>
      <div className="hidden text-right text-sm md:block">
        <p className="font-medium text-ink-900 tabular-nums">{formatDuration(row.minutes)}</p>
        <p className="text-steel-500">
          {row.percent !== null && <span className={cn('tabular-nums', row.percent < 50 && 'text-amber-600')}>{row.percent}%</span>}
          {row.sessions > 1 && <span className="ml-2">{t('{count} marta kirgan', { count: row.sessions })}</span>}
        </p>
      </div>
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
        counted.length > 0
          ? t('Oʻrtacha {time}', {
              time: formatDuration(counted.reduce((sum, row) => sum + row.minutes, 0) / counted.length),
            })
          : null,
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
            {lesson.group.name}
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
              <a href={`/api/lessons/${lesson.id}/attendance/export`} className={buttonClass({ variant: 'secondary' })}>
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
              {data.can_manage_students && (
                <Link to={`/groups/${lesson.group.id}/students`} className="text-sm text-azure-600 hover:underline">
                  {t('Guruh talabalari')}
                </Link>
              )}
            </div>
          }
        >
          {students.length === 0 ? (
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
                      <p className="truncate font-medium text-ink-900"><StaffName name={lesson.teacher.name} /></p>
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
                {counted.map((row) => (
                  <StudentRow key={row.student_id} row={row} from={from} to={to} />
                ))}
              </ul>
              {ignored.length > 0 && (
                <details className="border-t border-steel-100">
                  <summary className="cursor-pointer px-5 py-3 text-sm text-steel-600 hover:text-ink-900">
                    {t('Hisobga olinmaganlar: {count}', {
                      count: ignored.length,
                    })}
                  </summary>
                  <ul className="divide-y divide-steel-100">
                    {ignored.map((row) => (
                      <StudentRow key={row.student_id} row={row} from={from} to={to} muted />
                    ))}
                  </ul>
                </details>
              )}
            </>
          )}
        </Panel>
      )}

      {data.can_manage_students && !(tracking.synced && tracking.state === 'ended') && (
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
