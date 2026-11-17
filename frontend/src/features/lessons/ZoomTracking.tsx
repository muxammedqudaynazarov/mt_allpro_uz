import { Users, Video } from 'lucide-react'
import { OnAirDot } from '@/components/ui/Badge'
import { cn } from '@/components/ui/cn'
import { useT, type TFunction } from '@/i18n/I18nProvider'
import { formatDuration, formatTime } from '@/lib/format'
import type { Lesson } from '@/lib/types'

/** Host shundan ko'p kechiksa — ogohlantiruvchi rang (backend: ReportController::LATE_START_MINUTES). */
export const LATE_START_MINUTES = 5

export type DelayTone = 'ok' | 'mild' | 'late'

/** Host darsni jadvaldagi vaqtga nisbatan qachon boshlagani. */
export function startDelay(t: TFunction, minutes: number | null): { text: string; tone: DelayTone } | null {
  if (minutes === null) return null
  if (minutes < -1) return { text: t('{count} daqiqa erta', { count: -minutes }), tone: 'ok' }
  if (minutes <= 1) return { text: t('Oʻz vaqtida'), tone: 'ok' }
  return {
    text: t('{count} daqiqa kechikdi', { count: minutes }),
    tone: minutes > LATE_START_MINUTES ? 'late' : 'mild',
  }
}

export const delayToneClass: Record<DelayTone, string> = {
  ok: 'text-leaf-600',
  mild: 'text-steel-700',
  late: 'text-amber-600',
}

/**
 * Darslar ro'yxatida: dars Zoom'da haqiqatda qachon boshlangani, qancha davom etgani
 * va nechta talaba qatnashgani. Kuzatuv ma'lumoti bo'lmasa — hech narsa ko'rsatilmaydi.
 */
export function ZoomTrackingLine({ lesson, className }: { lesson: Lesson; className?: string }) {
  const t = useT()
  const tracking = lesson.tracking
  const delay = startDelay(t, tracking.start_delay_minutes)

  if (tracking.state === 'missed') {
    return <p className={cn('text-sm font-medium text-amber-600', className)}>{t('Zoom’da oʻtkazilmagan')}</p>
  }

  if (tracking.state === 'started' && tracking.started_at) {
    return (
      <p className={cn('flex flex-wrap items-center gap-x-3 gap-y-1 text-sm', className)}>
        <span className="inline-flex items-center gap-1.5 font-medium text-onair-600">
          <OnAirDot className="size-2" />
          {t('Zoom’da {time} dan beri', {
            time: formatTime(tracking.started_at),
          })}
        </span>
        {delay && <span className={delayToneClass[delay.tone]}>{delay.text}</span>}
      </p>
    )
  }

  if (tracking.state === 'ended' && tracking.started_at) {
    return (
      <p className={cn('flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-steel-600', className)}>
        <span className="inline-flex items-center gap-1.5 tabular-nums">
          <Video className="size-3.5 text-steel-500" aria-hidden />
          {formatTime(tracking.started_at)}
          {tracking.ended_at && `–${formatTime(tracking.ended_at)}`}
          {tracking.minutes !== null && <span className="text-steel-500">({formatDuration(tracking.minutes)})</span>}
        </span>
        {delay && <span className={delayToneClass[delay.tone]}>{delay.text}</span>}
        {tracking.participants !== null && (
          <span className="inline-flex items-center gap-1.5">
            <Users className="size-3.5 text-steel-500" aria-hidden />
            {t('{count} talaba', { count: tracking.participants })}
          </span>
        )}
      </p>
    )
  }

  // Jadval bo'yicha dars boshlangan, lekin host Zoom'da hali boshlamagan.
  if (lesson.status === 'live' && tracking.state === 'waiting') {
    return <p className={cn('text-sm font-medium text-amber-600', className)}>{t('Host Zoom’da hali boshlamagan')}</p>
  }

  return null
}

/**
 * Vaqt chizig'idagi bo'laklar: talaba (yoki host) Zoom'da bo'lgan oraliqlar.
 * `from`–`to` — umumiy o'q (masalan, dars Zoom'da boshlangan va tugagan vaqt).
 */
export function PresenceStrip({
  from,
  to,
  segments,
  tone = 'azure',
  label,
  className,
}: {
  from: number
  to: number
  segments: [string, string][]
  tone?: 'azure' | 'ink' | 'steel'
  label?: string
  className?: string
}) {
  const span = Math.max(1, to - from)
  const color = {
    azure: 'bg-azure-500',
    ink: 'bg-ink-800',
    steel: 'bg-steel-300',
  }[tone]

  return (
    <div className={cn('relative h-2.5 w-full rounded-full bg-steel-100', className)} role="img" aria-label={label}>
      {segments.map(([start, end]) => {
        const left = Math.min(100, Math.max(0, ((new Date(start).getTime() - from) / span) * 100))
        const right = Math.min(100, Math.max(0, ((new Date(end).getTime() - from) / span) * 100))
        return (
          <span
            key={start}
            className={cn('absolute inset-y-0 rounded-full', color)}
            style={{
              left: `${left}%`,
              width: `${Math.max(0.8, right - left)}%`,
            }}
          />
        )
      })}
    </div>
  )
}
