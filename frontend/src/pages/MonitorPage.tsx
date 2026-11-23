import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { CalendarClock, CheckCircle2, FileSpreadsheet, RadioTower, Webhook } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage } from '@/lib/api'
import { formatDuration, formatLongToday, formatTime } from '@/lib/format'
import type { Lesson, MonitorData } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { OnAirDot } from '@/components/ui/Badge'
import { buttonClass } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { LIVE_REFRESH_MS, LiveParticipantsPanel } from '@/features/lessons/LiveParticipants'
import { delayToneClass, startDelay } from '@/features/lessons/ZoomTracking'
import { useT } from '@/i18n/I18nProvider'
import { groupsLabel } from '@/lib/groups'
import { LectureBadge } from '@/features/lessons/LectureBadge'

function LiveLessonButton({ lesson, active, onSelect }: { lesson: Lesson; active: boolean; onSelect: () => void }) {
  const t = useT()
  const tracking = lesson.tracking
  const delay = startDelay(t, tracking.start_delay_minutes)
  const waiting = tracking.state !== 'started'
  return (
    <li>
      <button
        type="button"
        onClick={onSelect}
        aria-pressed={active}
        className={cn(
          'flex w-full items-center gap-4 px-5 py-3.5 text-left transition-colors',
          active ? 'bg-azure-50 shadow-[inset_3px_0_0_0_var(--color-azure-500)]' : 'hover:bg-mist/70',
        )}
      >
        <div className="min-w-0 flex-1">
          <p className="flex items-center gap-2 font-medium text-ink-900">
            {!waiting && <OnAirDot className="size-2" />}
            <span className="truncate" title={groupsLabel(lesson)}>
              {groupsLabel(lesson)}
            </span>
            {lesson.is_lecture && <LectureBadge />}
          </p>
          <p className="truncate text-sm text-steel-600"><StaffName name={lesson.teacher.name} /></p>
          <p className="mt-0.5 flex flex-wrap gap-x-2 text-xs text-steel-500 tabular-nums">
            {waiting ? (
              <span className="text-amber-600">{t('Host hali boshlamagan, jadval: {time}', { time: formatTime(lesson.starts_at) })}</span>
            ) : (
              <span>{t('Zoom’da {time} dan beri', { time: formatTime(tracking.started_at ?? lesson.starts_at) })}</span>
            )}
            {delay && <span className={delayToneClass[delay.tone]}>{delay.text}</span>}
          </p>
        </div>
        {tracking.present !== null && !waiting && (
          <div className="shrink-0 text-right">
            <p className="font-display text-2xl leading-none font-semibold text-ink-900 tabular-nums">{tracking.present}</p>
            <p className="mt-1 text-xs text-steel-500">{t('darsda')}</p>
          </div>
        )}
      </button>
    </li>
  )
}

export default function MonitorPage() {
  const t = useT()
  const { hasRole } = useAuth()
  const [params, setParams] = useSearchParams()

  const query = useQuery({
    queryKey: ['monitor'],
    queryFn: async () => (await api.get<{ data: MonitorData }>('/api/monitor')).data.data,
    refetchInterval: LIVE_REFRESH_MS,
  })

  if (query.isPending) return <SectionLoader />
  if (!query.data) return <EmptyState title={t('Maʼlumotni yuklab boʻlmadi')} description={errorMessage(query.error)} />

  const { live, upcoming, finished, webhooks } = query.data
  const selectedId = Number(params.get('lesson')) || live[0]?.id
  const selected = live.find((lesson) => lesson.id === selectedId) ?? live[0]

  function select(id: number) {
    setParams({ lesson: String(id) }, { replace: true })
    // Telefonda qatnashchilar roʻyxati pastda — unga oʻtamiz.
    if (window.matchMedia('(max-width: 1023px)').matches) {
      requestAnimationFrame(() => document.getElementById('live-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
    }
  }

  return (
    <>
      <PageHeader title={t('Monitoring')} description={`${formatLongToday()}. ${t('Efirdagi darslar va ulardagi qatnashchilar, roʻyxat har 30 soniyada yangilanadi.')}`} />

      {!webhooks && (
        <div className="mb-6 flex flex-col gap-3 rounded-field bg-amber-50 p-4 text-sm text-amber-600 sm:flex-row sm:items-center sm:justify-between">
          <p className="flex items-start gap-2.5">
            <Webhook className="mt-0.5 size-4 shrink-0" />
            {t('Zoom webhook hali ulanmagan: darslar holati daqiqada bir tekshiriladi, lekin jonli qatnashchilar roʻyxati faqat webhook orqali keladi.')}
          </p>
          {hasRole('super_admin') && (
            <Link to="/zoom-accounts" className={buttonClass({ variant: 'secondary', size: 'sm' })}>
              {t('Sozlash')}
            </Link>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <div className="space-y-6">
          <Panel
            padded={false}
            title={
              <span className="flex items-center gap-2">
                {t('Efirda')}
                <span className="font-display text-steel-500 tabular-nums">{live.length}</span>
              </span>
            }
          >
            {live.length === 0 ? (
              <EmptyState icon={<RadioTower className="size-5" />} title={t('Hozir efirda dars yoʻq')} description={t('Dars boshlanishi bilan shu yerda paydo boʻladi.')} />
            ) : (
              <ul className="divide-y divide-steel-100">
                {live.map((lesson) => (
                  <LiveLessonButton key={lesson.id} lesson={lesson} active={lesson.id === selected?.id} onSelect={() => select(lesson.id)} />
                ))}
              </ul>
            )}
          </Panel>

          {upcoming.length > 0 && (
            <Panel padded={false} title={t('Bugun kutilmoqda')}>
              <ul className="divide-y divide-steel-100 text-sm">
                {upcoming.map((lesson) => (
                  <li key={lesson.id} className="flex items-center gap-3 px-5 py-2.5">
                    <CalendarClock className="size-4 shrink-0 text-steel-400" />
                    <span className="w-11 shrink-0 font-medium tabular-nums">{formatTime(lesson.starts_at)}</span>
                    <span className="min-w-0 flex-1 truncate">
                      {lesson.is_lecture && `${t('Maʼruza')}: `}
                      {groupsLabel(lesson)}
                      <span className="text-steel-500">, <StaffName name={lesson.teacher.name} /></span>
                    </span>
                  </li>
                ))}
              </ul>
            </Panel>
          )}
        </div>

        <div id="live-detail" className="min-w-0 scroll-mt-20 space-y-6">
          {selected ? (
            <>
              <div>
                <h2 className="flex flex-wrap items-center gap-x-3 gap-y-1 font-display text-xl font-semibold text-ink-900">
                  {groupsLabel(selected)}
                  {selected.is_lecture && <LectureBadge />}
                </h2>
                <p className="mt-1 text-sm text-steel-600">
                  <StaffName name={selected.teacher.name} />
                  {selected.topic && selected.topic !== selected.group.name && !selected.topic.endsWith(groupsLabel(selected)) && `. ${selected.topic}`}
                </p>
                <p className="mt-0.5 text-sm text-steel-500 tabular-nums">
                  {t('Jadval: {from}–{to}', { from: formatTime(selected.starts_at), to: formatTime(selected.ends_at) })}
                  {selected.host && `. ${t('Host: {email}', { email: selected.host })}`}
                </p>
              </div>
              <LiveParticipantsPanel lesson={selected} />
            </>
          ) : (
            <Panel>
              <p className="text-sm text-steel-600">{t('Efirdagi darsni tanlang — uning qatnashchilari shu yerda koʻrinadi.')}</p>
            </Panel>
          )}

          <Panel padded={false} title={t('Bugun yakunlangan')}>
            {finished.length === 0 ? (
              <p className="px-5 py-4 text-sm text-steel-600">{t('Bugun hali yakunlangan dars yoʻq.')}</p>
            ) : (
              <ul className="divide-y divide-steel-100 text-sm">
                {finished.map((lesson) => (
                  <li key={lesson.id} className="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:gap-4">
                    <div className="flex min-w-0 flex-1 gap-4">
                      <span className="w-24 shrink-0 tabular-nums">
                        {formatTime(lesson.tracking.started_at ?? lesson.starts_at)}–{formatTime(lesson.tracking.ended_at ?? lesson.ended_at ?? lesson.ends_at)}
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="font-medium text-ink-900">
                          {lesson.is_lecture && `${t('Maʼruza')}: `}
                          {groupsLabel(lesson)}
                        </span>
                        <span className="text-steel-500">, <StaffName name={lesson.teacher.name} /></span>
                      </span>
                    </div>
                    <div className="flex items-center justify-between gap-4 sm:justify-end">
                      <span className="text-steel-600 tabular-nums">
                        {lesson.tracking.state === 'missed' ? (
                          <span className="text-amber-600">{t('Oʻtkazilmagan')}</span>
                        ) : lesson.tracking.synced ? (
                          <span className="inline-flex items-center gap-1.5">
                            <CheckCircle2 className="size-4 shrink-0 text-leaf-500" />
                            <span>
                              {t('{count} talaba', { count: lesson.tracking.participants ?? 0 })}
                              {lesson.tracking.minutes !== null && <span className="text-steel-500">, {formatDuration(lesson.tracking.minutes)}</span>}
                            </span>
                          </span>
                        ) : (
                          t('Hisobot kutilmoqda')
                        )}
                      </span>
                      <Link to={`/lessons/${lesson.id}`} className={buttonClass({ variant: 'secondary', size: 'sm' })}>
                        <FileSpreadsheet className="size-4" />
                        {t('Hisobot')}
                      </Link>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      </div>
    </>
  )
}
