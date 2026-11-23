import { Link } from 'react-router'
import { UsersRound } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { StaffName } from '@/components/ui/StaffName'
import { buttonClass } from '@/components/ui/Button'
import { useT } from '@/i18n/I18nProvider'
import { formatDayMonth, formatTime, isSameDay } from '@/lib/format'
import type { Lesson } from '@/lib/types'
import { groupsLabel } from '@/lib/groups'
import { LectureBadge } from './LectureBadge'
import { LessonActions } from './LessonActions'
import { LessonStatusBadge } from './LessonStatusBadge'
import { ZoomTrackingLine } from './ZoomTracking'

/** Darslar ro'yxatidagi bitta qator: mobil'da karta, kompyuterda keng qator. */
export function LessonRow({ lesson, showDate = true }: { lesson: Lesson; showDate?: boolean }) {
  const t = useT()
  const { user } = useAuth()
  const own = user?.id === lesson.teacher.id
  const date = showDate && !isSameDay(lesson.starts_at, new Date()) ? formatDayMonth(lesson.starts_at) : null

  return (
    <li className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:gap-6">
      <div className="flex shrink-0 items-baseline gap-2 md:block md:w-28">
        {date && <p className="text-sm text-steel-600 md:mb-0.5">{date}</p>}
        <p className="font-medium text-ink-900 tabular-nums">
          {formatTime(lesson.starts_at)}
          <span className="text-steel-500">–{formatTime(lesson.ends_at)}</span>
        </p>
        {lesson.slot && <p className="text-sm text-steel-600">{lesson.slot.name}</p>}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <Link
            to={`/lessons/${lesson.id}`}
            title={groupsLabel(lesson)}
            className="min-w-0 truncate font-medium text-ink-900 underline-offset-4 hover:text-azure-700 hover:underline"
          >
            {groupsLabel(lesson)}
          </Link>
          {lesson.is_lecture && <LectureBadge />}
          <LessonStatusBadge status={lesson.status} />
        </div>
        {!own && <p className="truncate text-sm text-steel-500"><StaffName name={lesson.teacher.name} /></p>}
        <ZoomTrackingLine lesson={lesson} className="mt-1.5" />
      </div>
      <LessonActions lesson={lesson} compact />
      {lesson.status === 'finished' && lesson.tracking.state === 'ended' && (
        <Link to={`/lessons/${lesson.id}`} className={buttonClass({ variant: 'secondary', size: 'sm' })}>
          <UsersRound className="size-4" />
          {t('Davomat')}
        </Link>
      )}
    </li>
  )
}
