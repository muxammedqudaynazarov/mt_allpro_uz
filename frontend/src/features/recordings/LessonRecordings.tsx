import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Download, FileText, Loader2, RotateCcw, Video } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/Button'
import { formatBytes, formatTime } from '@/lib/format'
import type { Lesson, Recording } from '@/lib/types'
import { cn } from '@/components/ui/cn'
import { Panel } from '@/components/ui/Page'
import { useT } from '@/i18n/I18nProvider'

const TYPE_LABELS: Record<string, string> = {
  MP4: 'Video',
  M4A: 'Audio',
  CHAT: 'Chat',
  TRANSCRIPT: 'Transkript',
  CC: 'Subtitr',
}

/** Xato bilan qolgan, lekin tizim hali o'zi qayta urinadigan fayl. */
function willRetry(item: Recording): boolean {
  return item.status === 'failed' && item.attempts < item.max_attempts
}

/** Darsning video yozuvi: ijro etish va fayllarni yuklab olish. */
export function LessonRecordings({ lesson }: { lesson: Lesson }) {
  const t = useT()
  const { isSuperAdmin } = useAuth()
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: ['lesson-recordings', lesson.id],
    queryFn: async () => (await api.get<{ data: Recording[] }>(`/api/lessons/${lesson.id}/recordings`)).data.data,
    // Zoom yozuvni tayyorlab, server yuklab olguncha sahifa yangilanib turadi.
    refetchInterval: (q) => (q.state.data?.some((item) => item.status === 'pending' || willRetry(item)) || (lesson.record && !q.state.data?.length) ? 60_000 : false),
  })
  const retry = useMutation({
    mutationFn: async (id: number) => api.post(`/api/recordings/${id}/retry`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['lesson-recordings', lesson.id] }),
  })
  const files = query.data ?? []
  const failed = files.find((item) => item.status === 'failed')
  const videos = files.filter((item) => item.file_type === 'MP4' && item.status === 'downloaded')
  const [active, setActive] = useState(0)
  const current = videos[Math.min(active, videos.length - 1)]

  if (!lesson.record && !files.length) return null
  if (lesson.status !== 'finished' && !files.length) return null

  return (
    <Panel
      padded={false}
      className="mb-6"
      title={
        <span className="flex items-center gap-2">
          <Video className="size-4 text-steel-500" />
          {t('Video yozuv')}
        </span>
      }
    >
      {current ? (
        <div className="bg-ink-900">
          <video key={current.id} controls preload="metadata" className="mx-auto aspect-video max-h-[32rem] w-full" src={current.url ?? undefined}>
            {t('Brauzeringiz videoni ijro eta olmaydi.')}
          </video>
        </div>
      ) : (
        failed ? (
          <div className="flex flex-wrap items-start gap-x-4 gap-y-3 px-5 py-4 text-sm">
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-600" />
            <div className="min-w-0 flex-1">
              <p className="text-ink-900">
                {willRetry(failed)
                  ? t('Video yozuvni yuklab boʻlmadi. Tizim 5 daqiqadan soʻng qayta urinadi ({attempts}/{max}).', { attempts: failed.attempts, max: failed.max_attempts })
                  : t('Video yozuvni yuklab boʻlmadi.')}
              </p>
              {failed.error && (
                <p className="mt-1 break-words text-steel-600">
                  {t('Sababi')}: {failed.error}
                </p>
              )}
            </div>
            {isSuperAdmin && !willRetry(failed) && (
              <Button size="sm" variant="secondary" loading={retry.isPending} onClick={() => retry.mutate(failed.id)}>
                <RotateCcw className="size-4" />
                {t('Qayta urinish')}
              </Button>
            )}
          </div>
        ) : (
          <p className="flex items-center gap-2.5 px-5 py-4 text-sm text-steel-600">
            <Loader2 className="size-4 animate-spin" />
            {files.length ? t('Video yozuv serverga koʻchirilmoqda.') : t('Zoom yozuvni tayyorlamoqda. Odatda dars davomiyligicha vaqt oladi.')}
          </p>
        )
      )}
      {files.length > 0 && (
        <ul className="divide-y divide-steel-100 border-t border-steel-100">
          {files.map((item) => {
            const videoIndex = videos.indexOf(item)
            return (
              <li key={item.id} className={cn('flex items-center gap-3 px-5 py-2.5 text-sm', videoIndex >= 0 && videoIndex === videos.indexOf(current) && 'bg-mist/60')}>
                {item.file_type === 'MP4' ? <Video className="size-4 shrink-0 text-steel-500" /> : <FileText className="size-4 shrink-0 text-steel-500" />}
                <span className="min-w-0 flex-1">
                  {videoIndex >= 0 && videos.length > 1 ? (
                    <button type="button" className="font-medium text-ink-900 hover:text-azure-700" onClick={() => setActive(videoIndex)}>
                      {t(TYPE_LABELS[item.file_type] ?? item.file_type)} {videoIndex + 1}
                    </button>
                  ) : (
                    <span className="font-medium text-ink-900">{t(TYPE_LABELS[item.file_type] ?? item.file_type)}</span>
                  )}
                  <span className="text-steel-500">
                    {item.recording_start && `, ${formatTime(item.recording_start)}`}
                    {item.recording_end && `–${formatTime(item.recording_end)}`}
                    {`, ${formatBytes(item.size)}`}
                  </span>
                </span>
                {item.status === 'downloaded' && item.url ? (
                  <a href={`${item.url}?download=1`} className="inline-flex items-center gap-1.5 text-azure-600 hover:underline">
                    <Download className="size-4" />
                    {t('Yuklab olish')}
                  </a>
                ) : item.status === 'failed' ? (
                  <span className="text-amber-600" title={item.error ?? undefined}>
                    {t('Xato')}
                  </span>
                ) : (
                  <span className="text-steel-500">{t('Navbatda')}</span>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </Panel>
  )
}
