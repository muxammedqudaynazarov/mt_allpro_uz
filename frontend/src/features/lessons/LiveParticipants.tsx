import { useQuery } from '@tanstack/react-query'
import { ExternalLink, RadioTower, UserRoundX } from 'lucide-react'
import { api } from '@/lib/api'
import { formatDuration, formatTime } from '@/lib/format'
import type { Lesson, LiveParticipant, LiveSnapshot } from '@/lib/types'
import { OnAirDot } from '@/components/ui/Badge'
import { buttonClass } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

/** Talab: ro'yxat kamida daqiqada bir yangilansin. Webhook bilan ma'lumot darhol keladi, sahifa esa 30 soniyada so'raydi. */
export const LIVE_REFRESH_MS = 30_000

export function useLiveParticipants(lessonId: number | null) {
  return useQuery({
    queryKey: ['lesson-live', lessonId],
    queryFn: async () => (await api.get<{ data: LiveSnapshot }>(`/api/lessons/${lessonId}/live`)).data.data,
    enabled: lessonId !== null,
    refetchInterval: LIVE_REFRESH_MS,
    refetchIntervalInBackground: false,
  })
}

/** "Zoom ochish": kuzatuvchi darsga oddiy qatnashchi sifatida kiradi (host emas). */
export function OpenZoomLink({ lesson, size = 'md', className }: { lesson: Lesson; size?: 'sm' | 'md'; className?: string }) {
  const t = useT()
  if (!lesson.join_url) return null
  return (
    <a href={lesson.join_url} target="_blank" rel="noopener noreferrer" className={buttonClass({ variant: 'secondary', size, className })}>
      <ExternalLink className="size-4" />
      {t('Zoom ochish')}
    </a>
  )
}

function Row({ row }: { row: LiveParticipant }) {
  const t = useT()
  return (
    <li className={cn('flex items-center gap-3 px-5 py-2.5', !row.present && 'text-steel-500')}>
      <span className={cn('size-2 shrink-0 rounded-full', row.present ? 'bg-leaf-500' : 'bg-steel-300')} aria-hidden />
      <div className="min-w-0 flex-1">
        <p className={cn('truncate', row.present ? 'font-medium text-ink-900' : 'text-steel-600')}>
          {row.name}
          {row.is_host && <span className="ml-2 text-xs font-normal text-steel-500">{t('host')}</span>}
        </p>
        <p className="text-xs text-steel-500 tabular-nums">
          {row.present
            ? t('{time} dan beri', { time: formatTime(row.joined_at) })
            : t('{from}–{to}, chiqib ketgan', { from: formatTime(row.joined_at), to: row.left_at ? formatTime(row.left_at) : '—' })}
        </p>
      </div>
      <span className="shrink-0 text-sm tabular-nums">{formatDuration(row.minutes)}</span>
    </li>
  )
}

export function LiveParticipantsPanel({ lesson, className }: { lesson: Lesson; className?: string }) {
  const t = useT()
  const query = useLiveParticipants(lesson.id)
  const data = query.data
  const present = data?.participants.filter((row) => row.present) ?? []
  const left = data?.participants.filter((row) => !row.present) ?? []
  const students = present.filter((row) => !row.is_host).length

  return (
    <Panel
      padded={false}
      className={className}
      title={
        <span className="flex items-center gap-2.5">
          <OnAirDot />
          {t('Hozir darsda')}
          {data && data.source !== 'none' && <span className="font-display text-ink-900 tabular-nums">{students}</span>}
        </span>
      }
      actions={<OpenZoomLink lesson={lesson} size="sm" />}
    >
      {query.isPending ? (
        <SectionLoader />
      ) : !data || data.source === 'none' ? (
        <EmptyState
          icon={<RadioTower className="size-5" />}
          title={t('Jonli roʻyxat mavjud emas')}
          description={
            data?.reason
              ? t('Zoom javobi: {reason}. Administrator Zoom akkauntida webhook’ni sozlashi kerak.', { reason: data.reason })
              : t('Zoom webhook sozlanmagan. Administrator Zoom akkauntlar sahifasida sozlagach, qatnashchilar shu yerda darhol koʻrinadi.')
          }
        />
      ) : data.participants.length === 0 ? (
        <EmptyState icon={<UserRoundX className="size-5" />} title={t('Hali hech kim kirmagan')} description={t('Qatnashchilar Zoom’ga kirishi bilan shu yerda paydo boʻladi.')} />
      ) : (
        <>
          <ul className="divide-y divide-steel-100">
            {present.map((row, index) => (
              <Row key={`p-${index}-${row.name}`} row={row} />
            ))}
          </ul>
          {left.length > 0 && (
            <details className="border-t border-steel-100">
              <summary className="cursor-pointer px-5 py-3 text-sm text-steel-600 hover:text-ink-900">{t('Chiqib ketganlar: {count}', { count: left.length })}</summary>
              <ul className="divide-y divide-steel-100">
                {left.map((row, index) => (
                  <Row key={`l-${index}-${row.name}`} row={row} />
                ))}
              </ul>
            </details>
          )}
        </>
      )}
      {data && (
        <p className="border-t border-steel-100 px-5 py-2.5 text-xs text-steel-500">
          {data.source === 'dashboard' ? `${t('Zoom Dashboard maʼlumoti')}. ` : data.source === 'webhook' ? `${t('Zoom’dan darhol keladi')}. ` : null}
          {t('Yangilandi: {time}', { time: formatTime(data.checked_at) })}
        </p>
      )}
    </Panel>
  )
}
