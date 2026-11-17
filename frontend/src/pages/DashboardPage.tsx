import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { CalendarPlus, Hourglass, Send } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api } from '@/lib/api'
import { formatLongToday, formatTime, greeting } from '@/lib/format'
import type { DashboardData, Lesson, LessonSlot } from '@/lib/types'
import { buttonClass } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { cn } from '@/components/ui/cn'
import { useNow } from '@/hooks/useNow'
import { LessonRow } from '@/features/lessons/LessonRow'
import { OnAirPanel } from '@/features/lessons/OnAirPanel'
import { useT } from '@/i18n/I18nProvider'
import { upperName } from '@/lib/name'

function minutesOfDay(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}

function tashkentMinutesNow(now: number): number {
  const [h, m] = formatTime(new Date(now)).split(':').map(Number)
  return h * 60 + m
}

function TodaySchedule({ slots, lessons }: { slots: LessonSlot[]; lessons: Lesson[] }) {
  const t = useT()
  const now = useNow()
  const current = tashkentMinutesNow(now)
  const unslotted = lessons.filter((lesson) => !lesson.slot || !slots.some((slot) => slot.id === lesson.slot?.id))

  if (!slots.length && !lessons.length) {
    return <EmptyState title={t('Bugun darslar yoʻq')} description={t('Yangi dars yaratsangiz, u shu yerda koʻrinadi.')} />
  }

  return (
    <ol className="divide-y divide-steel-100">
      {slots.map((slot, index) => {
        const inSlot = lessons.filter((lesson) => lesson.slot?.id === slot.id)
        const start = minutesOfDay(slot.starts_at)
        const end = minutesOfDay(slot.ends_at)
        const isNow = current >= start && current < end
        const passed = current >= end
        return (
          <li key={slot.id} className={cn(isNow && 'bg-azure-50/60')}>
            <div className="flex items-center gap-4 px-5 pt-4">
              <span
                className={cn(
                  'flex size-7 items-center justify-center rounded-full font-display text-xs font-semibold',
                  isNow ? 'bg-azure-500 text-white' : passed ? 'bg-steel-100 text-steel-500' : 'bg-ink-50 text-ink-700',
                )}
              >
                {index + 1}
              </span>
              <p className={cn('text-sm font-medium', passed && !isNow ? 'text-steel-500' : 'text-ink-900')}>
                {slot.label}
                <span className="ml-2 font-normal text-steel-500 tabular-nums">
                  {slot.starts_at}–{slot.ends_at}
                </span>
              </p>
              {isNow && <span className="text-xs font-medium text-azure-700">{t('hozir')}</span>}
            </div>
            {inSlot.length ? (
              <ul className="pb-1 pl-11">
                {inSlot.map((lesson) => (
                  <LessonRow key={lesson.id} lesson={lesson} showDate={false} />
                ))}
              </ul>
            ) : (
              <p className="pt-1 pb-4 pl-16 text-sm text-steel-500">{t('Dars yoʻq')}</p>
            )}
          </li>
        )
      })}
      {unslotted.length > 0 && (
        <li>
          <p className="px-5 pt-4 text-sm font-medium text-ink-900">{t('Boshqa vaqtlar')}</p>
          <ul>
            {unslotted.map((lesson) => (
              <LessonRow key={lesson.id} lesson={lesson} showDate={false} />
            ))}
          </ul>
        </li>
      )}
    </ol>
  )
}

export default function DashboardPage() {
  const t = useT()
  const { user, isSuperAdmin, canViewAll, can, config } = useAuth()
  const { data, isPending } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get<{ data: DashboardData }>('/api/dashboard')).data.data,
    refetchInterval: 30_000,
  })

  const firstName = upperName(user?.first_name ?? '')

  return (
    <>
      <PageHeader
        title={`${greeting()}, ${firstName}`}
        description={formatLongToday()}
        actions={
          can('lessons.create') && (
            <Link to="/lessons/new" className={buttonClass({ size: 'lg' })}>
              <CalendarPlus className="size-5" />
              {t('Dars yaratish')}
            </Link>
          )
        }
      />

      {isSuperAdmin && !!data?.stats.pending_users && (
        <Link
          to="/users?status=pending"
          className="mb-6 flex items-center gap-3 rounded-panel bg-amber-50 px-5 py-4 text-amber-600 ring-1 ring-amber-500/20 transition-colors hover:bg-amber-50/70"
        >
          <Hourglass className="size-5 shrink-0" />
          <span className="flex-1 text-[0.9375rem]">
            {t('{count} ta foydalanuvchi tasdiqlashingizni kutmoqda', { count: data.stats.pending_users })}
          </span>
          <span className="text-sm font-medium underline underline-offset-4">{t('Koʻrish')}</span>
        </Link>
      )}

      {config?.telegram_enabled && user && !user.telegram.linked && (
        <Link
          to="/profile"
          className="mb-6 flex items-center gap-3 rounded-panel bg-white px-5 py-4 ring-1 ring-steel-200 transition-colors hover:ring-azure-300"
        >
          <Send className="size-5 shrink-0 text-azure-500" />
          <span className="flex-1 text-[0.9375rem] text-ink-800">{t('Dars havolalari Telegram’ga kelishi uchun hisobingizni bot bilan ulang.')}</span>
          <span className="text-sm font-medium text-azure-600">{t('Ulash')}</span>
        </Link>
      )}

      {isPending ? (
        <SectionLoader />
      ) : data ? (
        <>
          <OnAirPanel lessons={data.live} />
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_17rem] 2xl:grid-cols-[minmax(0,1fr)_20rem]">
            <Panel title={t('Bugungi jadval')} padded={false}>
              <TodaySchedule slots={data.slots} lessons={data.today} />
            </Panel>
            <Panel title={t('Qisqacha')} padded={false} className="self-start">
              <dl className="divide-y divide-steel-100">
                {[
                  { label: t('Hozir efirda'), value: data.stats.live_now },
                  { label: t('Bugungi darslar'), value: data.stats.today_total },
                  { label: t('Shu oyda'), value: data.stats.month_total },
                ].map((item) => (
                  <div key={item.label} className="flex items-baseline justify-between px-5 py-4">
                    <dt className="text-sm text-steel-600">{item.label}</dt>
                    <dd className="font-display text-xl font-semibold tabular-nums">{item.value}</dd>
                  </div>
                ))}
              </dl>
              <p className="border-t border-steel-100 px-5 py-3 text-xs leading-relaxed text-steel-500">
                {canViewAll ? t('Barcha guruhlar boʻyicha.') : t('Siz yaratgan darslar boʻyicha.')}
              </p>
            </Panel>
          </div>
        </>
      ) : null}
    </>
  )
}
