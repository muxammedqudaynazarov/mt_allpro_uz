import { OnAirDot } from '@/components/ui/Badge'
import { useNow } from '@/hooks/useNow'
import { useAuth } from '@/auth/AuthProvider'
import { formatDuration, formatTime, minutesUntil } from '@/lib/format'
import type { Lesson } from '@/lib/types'
import { LessonActions } from './LessonActions'
import { useT } from '@/i18n/I18nProvider'

/**
 * "Efirda" paneli — hozir davom etayotgan darslar. Bosh sahifadagi eng
 * ko'zga tashlanadigan element: radio/TV studiyasidagi "ON AIR" chirog'iga o'xshaydi.
 */
export function OnAirPanel({ lessons }: { lessons: Lesson[] }) {
  const t = useT()
  const now = useNow(20_000)
  const { user } = useAuth()
  if (!lessons.length) return null

  return (
    <section aria-labelledby="onair-title" className="mb-8 overflow-hidden rounded-panel bg-ink-900 text-white">
      <header className="flex items-center gap-3 px-5 pt-5 sm:px-6">
        <OnAirDot />
        <h2 id="onair-title" className="font-display text-sm font-semibold tracking-wide text-onair-100">
          {t('Efirda')}
        </h2>
        <span className="text-sm text-ink-300">{lessons.length} ta dars</span>
      </header>
      <ul className="divide-y divide-white/10">
        {lessons.map((lesson) => {
          const total = Math.max(1, (new Date(lesson.ends_at).getTime() - new Date(lesson.starts_at).getTime()) / 60000)
          const left = minutesUntil(lesson.ends_at, now)
          const progress = Math.min(100, Math.max(0, ((total - left) / total) * 100))
          const own = user?.id === lesson.teacher.id
          return (
            <li key={lesson.id} className="px-5 py-5 sm:px-6">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0">
                  <p className="truncate text-xl font-semibold">{lesson.group.name}</p>
                  <p className="mt-1 truncate text-sm text-ink-200">{lesson.topic || t('Mavzu koʻrsatilmagan')}</p>
                  {!own && <p className="truncate text-sm text-ink-300">{lesson.teacher.name}</p>}
                </div>
                <div className="[&_button]:ring-white/20 [&_a]:ring-white/20">
                  <LessonActions lesson={lesson} compact />
                </div>
              </div>
              <div className="mt-4 flex items-center gap-4 text-sm text-ink-200">
                <span className="tabular-nums">{formatTime(lesson.starts_at)}</span>
                <div className="h-1 flex-1 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100} aria-label={t('Dars davomiyligi')}>
                  <div className="h-full rounded-full bg-azure-400" style={{ width: `${progress}%` }} />
                </div>
                <span className="tabular-nums">{formatTime(lesson.ends_at)}</span>
                <span className="hidden w-36 text-right text-ink-100 sm:block">{left > 0 ? t('{time} qoldi', { time: formatDuration(left) }) : t('Vaqti tugadi')}</span>
              </div>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
