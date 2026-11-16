import { useState } from 'react'
import { Copy, Play, Send, Square } from 'lucide-react'
import { Button, buttonClass } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import type { Lesson } from '@/lib/types'
import { copyText, telegramShareUrl, useLessonActions } from './useLessonActions'

export function LessonActions({ lesson, compact = false }: { lesson: Lesson; compact?: boolean }) {
  const { start, end } = useLessonActions()
  const [confirmEnd, setConfirmEnd] = useState(false)
  const active = lesson.status === 'live' || lesson.status === 'scheduled'
  if (!active) return null

  return (
    <div className="flex flex-wrap items-center gap-2">
      {lesson.can.start && (
        <Button
          size={compact ? 'sm' : 'md'}
          icon={<Play className="size-4" />}
          loading={start.isPending && start.variables?.id === lesson.id}
          onClick={() => start.mutate(lesson)}
        >
          Darsni boshlash
        </Button>
      )}
      {lesson.join_url && (
        <>
          <Button size={compact ? 'sm' : 'md'} variant="secondary" icon={<Copy className="size-4" />} onClick={() => copyText(lesson.join_url!)}>
            {compact ? 'Nusxalash' : 'Havolani nusxalash'}
          </Button>
          <a
            href={telegramShareUrl(lesson)}
            target="_blank"
            rel="noopener noreferrer"
            className={buttonClass({ variant: 'secondary', size: compact ? 'sm' : 'md' })}
          >
            <Send className="size-4" />
            Telegram
          </a>
        </>
      )}
      {lesson.can.end && (
        <Button size={compact ? 'sm' : 'md'} variant="danger" icon={<Square className="size-3.5" />} onClick={() => setConfirmEnd(true)}>
          Yakunlash
        </Button>
      )}
      <ConfirmDialog
        open={confirmEnd}
        title="Darsni yakunlaysizmi?"
        description={`${lesson.group.name} guruhidagi Zoom uchrashuvi barcha ishtirokchilar uchun yopiladi. Bu amalni ortga qaytarib boʻlmaydi.`}
        confirmLabel="Darsni yakunlash"
        tone="danger"
        loading={end.isPending}
        onClose={() => setConfirmEnd(false)}
        onConfirm={() => end.mutate(lesson, { onSettled: () => setConfirmEnd(false) })}
      />
    </div>
  )
}
