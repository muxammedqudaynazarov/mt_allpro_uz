import { useState } from 'react'
import { Copy, Play, Send, Square } from 'lucide-react'
import { Button, buttonClass } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import type { Lesson } from '@/lib/types'
import { copyText, telegramShareUrl, useLessonActions } from './useLessonActions'
import { useT } from '@/i18n/I18nProvider'
import { groupsLabel } from '@/lib/groups'

export function LessonActions({ lesson, compact = false }: { lesson: Lesson; compact?: boolean }) {
  const t = useT()
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
          {t('Darsni boshlash')}
        </Button>
      )}
      {lesson.join_url && (
        <>
          <Button size={compact ? 'sm' : 'md'} variant="secondary" icon={<Copy className="size-4" />} onClick={() => copyText(lesson.join_url!)}>
            {compact ? t('Nusxalash') : t('Havolani nusxalash')}
          </Button>
          <a
            href={telegramShareUrl(lesson)}
            target="_blank"
            rel="noopener noreferrer"
            className={buttonClass({ variant: 'secondary', size: compact ? 'sm' : 'md' })}
          >
            <Send className="size-4" />
            {t('Telegram')}
          </a>
        </>
      )}
      {lesson.can.end && (
        <Button size={compact ? 'sm' : 'md'} variant="danger" icon={<Square className="size-3.5" />} onClick={() => setConfirmEnd(true)}>
          {t('Yakunlash')}
        </Button>
      )}
      <ConfirmDialog
        open={confirmEnd}
        title={t('Darsni yakunlaysizmi?')}
        description={
          lesson.is_lecture
            ? t('Maʼruzaning ({groups}) Zoom uchrashuvi barcha ishtirokchilar uchun yopiladi. Bu amalni ortga qaytarib boʻlmaydi.', { groups: groupsLabel(lesson) })
            : t('{group} guruhidagi Zoom uchrashuvi barcha ishtirokchilar uchun yopiladi. Bu amalni ortga qaytarib boʻlmaydi.', { group: lesson.group.name })
        }
        confirmLabel={t('Darsni yakunlash')}
        tone="danger"
        loading={end.isPending}
        onClose={() => setConfirmEnd(false)}
        onConfirm={() => end.mutate(lesson, { onSettled: () => setConfirmEnd(false) })}
      />
    </div>
  )
}
