import { useMemo, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, Plus, Presentation, Repeat } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage } from '@/lib/api'
import { addDays, dayDate, formatDayMonth, formatTime, formatWeekday, isoDate, isoWeekday, weekStart } from '@/lib/format'
import { groupsLabel } from '@/lib/groups'
import type { CalendarData, Group, Lesson, PlannedLesson } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { OnAirDot } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { Select } from '@/components/ui/Field'
import { PageHeader } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { PlannedModal, SeriesList, SeriesModal } from '@/features/calendar/series'
import { useT } from '@/i18n/I18nProvider'

type Item = { kind: 'lesson'; lesson: Lesson } | { kind: 'planned'; planned: PlannedLesson }

const OTHER = 'other'

function itemStart(item: Item) {
  return item.kind === 'lesson' ? item.lesson.starts_at : item.planned.starts_at
}

function LessonCard({ lesson, showTeacher, showTime }: { lesson: Lesson; showTeacher: boolean; showTime?: boolean }) {
  const t = useT()
  const missed = lesson.tracking.state === 'missed'
  const tone =
    lesson.status === 'live'
      ? 'bg-onair-50 ring-onair-500/30'
      : lesson.status === 'scheduled'
        ? 'bg-azure-50 ring-azure-500/30'
        : lesson.status === 'cancelled'
          ? 'bg-white ring-steel-200 text-steel-500'
          : missed
            ? 'bg-amber-50 ring-amber-500/30'
            : 'bg-steel-50 ring-steel-200'
  return (
    <Link
      to={`/lessons/${lesson.id}`}
      className={cn('block rounded-[0.5rem] px-2.5 py-2 text-left text-[0.8125rem] leading-snug ring-1 transition hover:brightness-[0.97] ring-inset', tone)}
    >
      <span className={cn('flex items-center gap-1.5 font-medium text-ink-900', lesson.status === 'cancelled' && 'text-steel-500 line-through')}>
        {lesson.status === 'live' && <OnAirDot className="size-2 shrink-0" />}
        {lesson.series_id && <Repeat className="size-3 shrink-0 text-steel-500" aria-label={t('Haftalik dars')} />}
        {lesson.is_lecture && <Presentation className="size-3 shrink-0 text-ink-600" aria-label={t('Maʼruza')} />}
        <span className="truncate" title={groupsLabel(lesson)}>
          {groupsLabel(lesson)}
        </span>
      </span>
      {showTeacher && <StaffName name={lesson.teacher.name} className="block truncate text-steel-600" />}
      <span className="block text-steel-500 tabular-nums">
        {showTime && `${formatTime(lesson.starts_at)}–${formatTime(lesson.ends_at)}. `}
        {lesson.status === 'cancelled'
          ? t('Bekor qilingan')
          : missed
            ? t('Oʻtkazilmagan')
            : lesson.status === 'finished' && lesson.tracking.participants !== null
              ? t('{count} talaba', { count: lesson.tracking.participants })
              : lesson.status === 'live'
                ? t('Efirda')
                : t('Zoom tayyor')}
      </span>
    </Link>
  )
}

function PlannedCard({ item, showTeacher, showTime, onOpen }: { item: PlannedLesson; showTeacher: boolean; showTime?: boolean; onOpen: () => void }) {
  const t = useT()
  return (
    <button
      type="button"
      onClick={onOpen}
      className={cn(
        'block w-full rounded-[0.5rem] border border-dashed px-2.5 py-2 text-left text-[0.8125rem] leading-snug transition-colors',
        item.skipped ? 'border-steel-200 bg-transparent text-steel-500' : 'border-steel-300 bg-white hover:border-azure-500',
        item.error && 'border-onair-500',
      )}
    >
      <span className={cn('flex items-center gap-1.5 font-medium', item.skipped ? 'text-steel-500 line-through' : 'text-ink-900')}>
        <Repeat className="size-3 shrink-0 text-steel-500" />
        {item.is_lecture && <Presentation className="size-3 shrink-0 text-ink-600" aria-label={t('Maʼruza')} />}
        <span className="truncate" title={groupsLabel(item)}>
          {groupsLabel(item)}
        </span>
      </span>
      {showTeacher && <StaffName name={item.teacher.name} className="block truncate text-steel-600" />}
      <span className={cn('block tabular-nums', item.error ? 'text-onair-600' : 'text-steel-500')}>
        {showTime && `${formatTime(item.starts_at)}–${formatTime(item.ends_at)}. `}
        {item.skipped ? t('Bu kun bekor qilingan') : item.error ? t('Yaratilmadi') : t('Rejada')}
      </span>
    </button>
  )
}

function AddLink({ date, slot }: { date: string; slot: number }) {
  const t = useT()
  return (
    <Link
      to={`/lessons/new?date=${date}&slot=${slot}`}
      className="flex h-full min-h-14 items-center justify-center rounded-[0.5rem] text-steel-400 opacity-0 ring-1 ring-steel-200 transition-opacity ring-inset group-hover:opacity-100 hover:text-azure-600 focus-visible:opacity-100"
      aria-label={t('Shu vaqtga dars yaratish')}
    >
      <Plus className="size-4" />
    </Link>
  )
}

export default function CalendarPage() {
  const t = useT()
  const { canViewAll, can, config } = useAuth()
  // Dam olish kunlari (yakshanba): dars qo'shib bo'lmaydi.
  const isClosed = (day: string) => (config?.closed_weekdays ?? [7]).includes(isoWeekday(day))
  const [params, setParams] = useSearchParams()
  const [groupId, setGroupId] = useState('')
  const [planned, setPlanned] = useState<PlannedLesson | null>(null)
  const [adding, setAdding] = useState(false)
  const today = isoDate()
  const week = weekStart(params.get('week') ?? today)
  const to = isoDate(addDays(dayDate(week), 6))

  const query = useQuery({
    queryKey: ['calendar', week, groupId],
    queryFn: async () => (await api.get<{ data: CalendarData }>('/api/calendar', { params: { from: week, to, group_id: groupId || undefined } })).data.data,
    placeholderData: (previous) => previous,
    refetchInterval: 60_000,
  })
  const groups = useQuery({
    queryKey: ['groups'],
    queryFn: async () => (await api.get<{ data: Group[] }>('/api/groups')).data.data,
    enabled: canViewAll,
  })

  const data = query.data
  const canCreate = !!data?.can_manage && can('lessons.create')
  const showTeacher = canViewAll

  const items = useMemo<Item[]>(
    () => [...(data?.lessons ?? []).map((lesson) => ({ kind: 'lesson' as const, lesson })), ...(data?.planned ?? []).map((item) => ({ kind: 'planned' as const, planned: item }))],
    [data],
  )

  const days = useMemo(() => {
    const all = Array.from({ length: 7 }, (_, index) => isoDate(addDays(dayDate(week), index)))
    const sunday = all[6]
    const hasSunday = items.some((item) => isoDate(itemStart(item)) === sunday)
    return hasSunday ? all : all.slice(0, 6)
  }, [week, items])

  // Juftliksiz yaratilgan dars ("Hozir") juftlik vaqtida boshlangan bo'lsa — o'sha qatorga.
  const slotOf = (item: Item): number | undefined => {
    if (item.kind === 'planned') return item.planned.slot.id
    if (item.lesson.slot) return item.lesson.slot.id
    return data?.slots.find((slot) => slot.starts_at === formatTime(item.lesson.starts_at))?.id
  }

  const cell = (day: string, row: string): Item[] =>
    items
      .filter((item) => {
        if (isoDate(itemStart(item)) !== day) return false
        const slotId = slotOf(item)
        const known = slotId !== undefined && data?.slots.some((slot) => slot.id === slotId)
        return row === OTHER ? !known : String(slotId) === row
      })
      .sort((a, b) => itemStart(a).localeCompare(itemStart(b)))

  const hasOther = days.some((day) => cell(day, OTHER).length > 0)
  const rows = [...(data?.slots ?? []).map((slot) => ({ key: String(slot.id), slot })), ...(hasOther ? [{ key: OTHER, slot: null }] : [])]
  const now = Date.now()

  const go = (offset: number) => setParams({ week: isoDate(addDays(dayDate(week), offset * 7)) }, { replace: true })
  const first = dayDate(week)
  const last = dayDate(days[days.length - 1])
  const range = `${formatDayMonth(first)} – ${formatDayMonth(last)}`

  const render = (item: Item, time = false): ReactNode =>
    item.kind === 'lesson' ? (
      <LessonCard key={`l${item.lesson.id}`} lesson={item.lesson} showTeacher={showTeacher} showTime={time} />
    ) : (
      <PlannedCard key={`p${item.planned.series_id}-${item.planned.date}`} item={item.planned} showTeacher={showTeacher} showTime={time} onOpen={() => setPlanned(item.planned)} />
    )

  return (
    <>
      <PageHeader
        title={t('Kalendar')}
        description={
          canCreate
            ? t('Haftalik darslar shu yerda rejada koʻrinadi. Zoom uchrashuvi dars kuni, boshlanishidan 1 soat oldin yaratiladi.')
            : canViewAll
              ? t('Barcha oʻqituvchilarning darslari va haftalik jadvallari.')
              : t('Sizning darslaringiz va haftalik jadvalingiz.')
        }
        actions={
          canCreate && (
            <Button icon={<Repeat className="size-4" />} onClick={() => setAdding(true)}>
              {t('Haftalik dars qoʻshish')}
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2">
          <Button variant="secondary" size="sm" className="w-9 px-0" onClick={() => go(-1)} aria-label={t('Oldingi hafta')}>
            <ChevronLeft className="size-4" />
          </Button>
          <Button variant="secondary" size="sm" onClick={() => setParams({}, { replace: true })} disabled={week === weekStart(today)}>
            {t('Bu hafta')}
          </Button>
          <Button variant="secondary" size="sm" className="w-9 px-0" onClick={() => go(1)} aria-label={t('Keyingi hafta')}>
            <ChevronRight className="size-4" />
          </Button>
          <p className="ml-2 font-display text-lg font-semibold text-ink-900 tabular-nums">{range}</p>
        </div>
        {canViewAll && (
          <Select aria-label={t('Guruh')} wrapperClassName="sm:w-72" value={groupId} onChange={(e) => setGroupId(e.target.value)}>
            <option value="">{t('Barcha guruhlar')}</option>
            {groups.data?.map((group) => (
              <option key={group.id} value={group.id}>
                {group.name}
              </option>
            ))}
          </Select>
        )}
      </div>

      {query.isPending ? (
        <SectionLoader />
      ) : !data ? (
        <EmptyState title={t('Maʼlumotni yuklab boʻlmadi')} description={errorMessage(query.error)} />
      ) : (
        <>
          {/* Kompyuter: hafta jadvali (juftlik × kun). */}
          <div className="hidden overflow-x-auto rounded-panel bg-white ring-1 ring-steel-200/70 md:block">
            <table className="w-full min-w-[56rem] table-fixed border-collapse text-sm">
              <thead>
                <tr className="border-b border-steel-100">
                  <th className="w-32 px-3 py-3" />
                  {days.map((day) => {
                    const date = dayDate(day)
                    const isToday = day === today
                    return (
                      <th key={day} className="px-1.5 py-3 text-left font-normal">
                        <span className={cn('inline-flex items-baseline gap-1.5 rounded-full px-2.5 py-1', isToday && 'bg-ink-900 text-white')}>
                          <span className={cn('capitalize', isToday ? 'text-steel-200' : 'text-steel-600')}>{formatWeekday(date)}</span>
                          <span className="font-semibold tabular-nums">{formatDayMonth(date).split('-')[0]}</span>
                        </span>
                        {isClosed(day) && <span className="mt-0.5 block px-2.5 text-xs text-steel-500">{t('Dam olish kuni')}</span>}
                      </th>
                    )
                  })}
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {rows.map((row) => (
                  <tr key={row.key}>
                    <th scope="row" className="px-3 py-2.5 text-left align-top font-normal">
                      {row.slot ? (
                        <>
                          <span className="block font-medium text-ink-900">{row.slot.name}</span>
                          <span className="block whitespace-nowrap text-steel-500 tabular-nums">
                            {row.slot.starts_at}–{row.slot.ends_at}
                          </span>
                        </>
                      ) : (
                        <span className="block text-steel-600">{t('Boshqa vaqt')}</span>
                      )}
                    </th>
                    {days.map((day) => {
                      const content = cell(day, row.key)
                      const slotEnd = row.slot ? new Date(`${day}T${row.slot.ends_at}:00+05:00`).getTime() : 0
                      const addable = canCreate && !isClosed(day) && row.slot && content.every((item) => item.kind === 'lesson' && item.lesson.status === 'cancelled') && slotEnd > now
                      return (
                        <td key={day} className={cn('group px-1.5 py-1.5 align-top', day === today && 'bg-mist/50')}>
                          <div className="flex min-h-14 flex-col gap-1.5">
                            {content.map((item) => render(item, row.key === OTHER))}
                            {addable && row.slot && <AddLink date={day} slot={row.slot.id} />}
                          </div>
                        </td>
                      )
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Telefon: kunlar bo'yicha ro'yxat. */}
          <div className="space-y-4 md:hidden">
            {days.map((day) => {
              const content = items.filter((item) => isoDate(itemStart(item)) === day).sort((a, b) => itemStart(a).localeCompare(itemStart(b)))
              if (!content.length && day !== today) return null
              const date = dayDate(day)
              return (
                <section key={day}>
                  <h2 className={cn('mb-2 text-sm font-semibold first-letter:uppercase', day === today ? 'text-azure-600' : 'text-ink-900')}>
                    {formatWeekday(date)}, {formatDayMonth(date)}
                  </h2>
                  {content.length ? (
                    <div className="space-y-2">{content.map((item) => render(item, true))}</div>
                  ) : (
                    <p className="text-sm text-steel-500">{isClosed(day) ? t('Dam olish kuni') : t('Bu kunda dars yoʻq')}</p>
                  )}
                </section>
              )
            })}
          </div>

          <ul className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-steel-600" aria-label={t('Belgilar')}>
            <li className="flex items-center gap-2">
              <span className="h-3 w-5 rounded-sm border border-dashed border-steel-300 bg-white" />
              {t('Rejada: Zoom uchrashuvi hali yaratilmagan')}
            </li>
            <li className="flex items-center gap-2">
              <span className="h-3 w-5 rounded-sm bg-azure-50 ring-1 ring-azure-500/30" />
              {t('Zoom tayyor')}
            </li>
            <li className="flex items-center gap-2">
              <span className="h-3 w-5 rounded-sm bg-onair-50 ring-1 ring-onair-500/30" />
              {t('Efirda')}
            </li>
            <li className="flex items-center gap-2">
              <span className="h-3 w-5 rounded-sm bg-steel-50 ring-1 ring-steel-200" />
              {t('Yakunlangan')}
            </li>
          </ul>

          <div className="mt-8">
            <SeriesList params={groupId ? { group_id: groupId } : {}} onAdd={canCreate ? () => setAdding(true) : undefined} />
          </div>
        </>
      )}

      <PlannedModal item={planned} onClose={() => setPlanned(null)} leadMinutes={data?.lead_minutes ?? 60} />
      {adding && <SeriesModal open={adding} onClose={() => setAdding(false)} />}
    </>
  )
}
