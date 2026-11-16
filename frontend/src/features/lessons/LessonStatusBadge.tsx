import { Badge, OnAirDot } from '@/components/ui/Badge'
import { LESSON_STATUS_LABELS } from '@/lib/labels'
import type { LessonStatus } from '@/lib/types'

export function LessonStatusBadge({ status }: { status: LessonStatus }) {
  if (status === 'live') {
    return (
      <Badge tone="onair" className="gap-2">
        <OnAirDot className="size-2" />
        {LESSON_STATUS_LABELS.live}
      </Badge>
    )
  }
  const tone = status === 'scheduled' ? 'azure' : status === 'finished' ? 'neutral' : 'amber'
  return <Badge tone={tone}>{LESSON_STATUS_LABELS[status]}</Badge>
}
