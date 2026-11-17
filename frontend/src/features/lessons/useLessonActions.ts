import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { tGlobal as t } from '@/i18n/I18nProvider'
import { api, errorMessage } from '@/lib/api'
import { formatTime } from '@/lib/format'
import type { Lesson } from '@/lib/types'

export function telegramShareUrl(lesson: Lesson): string {
  const lines = [
    `${lesson.group.name}`,
    lesson.topic ? t('Mavzu: {topic}', { topic: lesson.topic }) : null,
    t('Vaqti: {time}', { time: `${formatTime(lesson.starts_at)}–${formatTime(lesson.ends_at)}` }),
    lesson.passcode ? t('Kod: {code}', { code: lesson.passcode }) : null,
  ].filter(Boolean)
  const params = new URLSearchParams({ url: lesson.join_url ?? '', text: lines.join('\n') })
  return `https://t.me/share/url?${params.toString()}`
}

export async function copyText(text: string, success?: string) {
  try {
    await navigator.clipboard.writeText(text)
    toast.success(success ?? t('Havola nusxalandi'))
  } catch {
    toast.error(t('Nusxalab boʻlmadi. Havolani qoʻlda belgilab oling.'))
  }
}

export function useLessonActions() {
  const queryClient = useQueryClient()

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['lessons'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    queryClient.invalidateQueries({ queryKey: ['lesson-options'] })
  }

  const start = useMutation({
    mutationFn: async (lesson: Lesson) => {
      // Brauzer popup'ni bloklamasligi uchun oynani darhol ochib, keyin manzilni beramiz.
      const popup = window.open('about:blank', '_blank')
      try {
        const { data } = await api.post<{ data: { url: string } }>(`/api/lessons/${lesson.id}/start`)
        if (popup) {
          popup.opener = null
          popup.location.href = data.data.url
        } else {
          window.location.href = data.data.url
        }
      } catch (error) {
        popup?.close()
        throw error
      }
    },
    onSuccess: invalidate,
    onError: (error) => toast.error(errorMessage(error, t('Darsni boshlab boʻlmadi.'))),
  })

  const end = useMutation({
    mutationFn: async (lesson: Lesson) => (await api.post<{ data: Lesson }>(`/api/lessons/${lesson.id}/end`)).data.data,
    onSuccess: () => {
      toast.success(t('Dars yakunlandi'))
      invalidate()
    },
    onError: (error) => toast.error(errorMessage(error, t('Darsni yakunlab boʻlmadi.'))),
  })

  return { start, end, invalidate }
}
