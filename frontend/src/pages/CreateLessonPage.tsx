import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Check, Copy, Play, Send, Zap } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { formatDuration, formatTime } from '@/lib/format'
import type { Lesson, LessonOptions } from '@/lib/types'
import { Button, buttonClass } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader } from '@/components/ui/Page'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { cn } from '@/components/ui/cn'
import { copyText, telegramShareUrl, useLessonActions } from '@/features/lessons/useLessonActions'
import { useT } from '@/i18n/I18nProvider'

type SlotChoice = 'now' | number

function Step({ index, title, hint, children }: { index: number; title: string; hint?: string; children: ReactNode }) {
  return (
    <section className="group relative pb-9 pl-12 last:pb-0" aria-labelledby={`step-${index}`}>
      <span className="absolute top-0 bottom-0 left-[0.9rem] w-px bg-steel-200 group-last:hidden" aria-hidden />
      <span className="absolute top-0 left-0 flex size-7 items-center justify-center rounded-full bg-ink-900 font-display text-xs font-semibold text-white">
        {index}
      </span>
      <h2 id={`step-${index}`} className="pt-0.5 text-base font-semibold text-ink-900">
        {title}
      </h2>
      {hint && <p className="mt-1 text-sm text-steel-600">{hint}</p>}
      <div className="mt-4">{children}</div>
    </section>
  )
}

function CreatedModal({ lesson, onClose }: { lesson: Lesson | null; onClose: () => void }) {
  const t = useT()
  const { user } = useAuth()
  const { start } = useLessonActions()
  if (!lesson) return null
  return (
    <Modal
      open={!!lesson}
      onClose={onClose}
      title={t('Dars yaratildi')}
      description={`${lesson.group.name}, ${formatTime(lesson.starts_at)}–${formatTime(lesson.ends_at)}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bosh sahifaga qaytish')}
          </Button>
          {lesson.can.start && (
            <Button icon={<Play className="size-4" />} loading={start.isPending} onClick={() => start.mutate(lesson)}>
              {t('Darsni boshlash')}
            </Button>
          )}
        </>
      }
    >
      <div className="space-y-5">
        <div>
          <p className="text-sm font-medium text-ink-800">{t('Talabalar uchun havola')}</p>
          <div className="mt-2 flex gap-2">
            <input
              readOnly
              value={lesson.join_url ?? ''}
              onFocus={(event) => event.currentTarget.select()}
              className="h-11 min-w-0 flex-1 rounded-field bg-steel-50 px-3.5 text-sm text-ink-800 ring-1 ring-steel-200 ring-inset"
              aria-label={t('Talabalar uchun havola')}
            />
            <Button variant="secondary" className="h-11" icon={<Copy className="size-4" />} onClick={() => copyText(lesson.join_url ?? '')}>
              {t('Nusxalash')}
            </Button>
          </div>
          {lesson.passcode && (
            <p className="mt-2 text-sm text-steel-600">
              {t('Kirish kodi:')} <span className="font-semibold text-ink-900 tabular-nums">{lesson.passcode}</span>
            </p>
          )}
        </div>
        <a href={telegramShareUrl(lesson)} target="_blank" rel="noopener noreferrer" className={buttonClass({ variant: 'secondary', className: 'w-full' })}>
          <Send className="size-4" />
          {t('Guruhga Telegram orqali yuborish')}
        </a>
        {user?.telegram.linked && (
          <p className="flex items-center gap-2 text-sm text-leaf-600">
            <Check className="size-4" />
            {t('Havolalar Telegram’ingizga ham yuborildi.')}
          </p>
        )}
      </div>
    </Modal>
  )
}

export default function CreateLessonPage() {
  const t = useT()
  const navigate = useNavigate()
  const [slot, setSlot] = useState<SlotChoice>('now')
  const [course, setCourse] = useState<string>('')
  const [groupId, setGroupId] = useState<number | null>(null)
  const [topic, setTopic] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [created, setCreated] = useState<Lesson | null>(null)

  const options = useQuery({
    queryKey: ['lesson-options', slot],
    queryFn: async () => (await api.get<{ data: LessonOptions }>('/api/lessons/options', { params: { slot_id: slot } })).data.data,
    placeholderData: (previous) => previous,
    refetchInterval: 30_000,
  })

  const courses = useMemo(() => {
    const set = new Set(options.data?.groups.map((group) => group.course))
    return [...set].sort((a, b) => a - b)
  }, [options.data])

  useEffect(() => {
    if (!course && courses.length) setCourse(String(courses[0]))
  }, [course, courses])

  const groups = options.data?.groups.filter((group) => String(group.course) === course) ?? []
  const selectedGroup = options.data?.groups.find((group) => group.id === groupId)
  const selectedSlot = slot === 'now' ? null : options.data?.slots.find((item) => item.id === slot)

  useEffect(() => {
    // Tanlangan guruh band bo'lib qolsa, tanlovni bekor qilamiz.
    if (selectedGroup?.busy) setGroupId(null)
  }, [selectedGroup?.busy])

  const create = useMutation({
    mutationFn: async () =>
      (await api.post<{ data: Lesson }>('/api/lessons', { group_id: groupId, slot_id: slot === 'now' ? null : slot, topic: topic.trim() || null })).data
        .data,
    onSuccess: (lesson) => {
      setCreated(lesson)
      setTopic('')
      setGroupId(null)
      options.refetch()
    },
    onError: (error) => {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) toast.error(errorMessage(error, t('Darsni yaratib boʻlmadi.')))
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    setErrors({})
    create.mutate()
  }

  const duration = selectedSlot?.duration_minutes ?? options.data?.default_duration ?? 80

  return (
    <>
      <PageHeader title={t('Dars yaratish')} description={t('Vaqt va guruhni tanlang — Zoom uchrashuvi avtomatik yaratiladi va havola sizga yuboriladi.')} />

      {options.isPending ? (
        <SectionLoader />
      ) : !options.data?.groups.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          title={t('Faol guruhlar yoʻq')}
          description={t('Dars yaratish uchun administrator kamida bitta guruhni Zoom akkauntiga biriktirib, faollashtirishi kerak.')}
        />
      ) : (
        <form onSubmit={submit} className="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <div className="rounded-panel bg-white p-6 ring-1 ring-steel-200/70 sm:p-8">
            <Step index={1} title={t('Qachon?')} hint={t('Bugungi paralardan birini yoki darhol boshlashni tanlang.')}>
              <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={t('Dars vaqti')}>
                <SlotPill active={slot === 'now'} onClick={() => setSlot('now')}>
                  <Zap className="size-4" />
                  {t('Hozir, {time}', { time: formatDuration(options.data.default_duration) })}
                </SlotPill>
                {options.data.slots.map((item) => (
                  <SlotPill key={item.id} active={slot === item.id} disabled={!item.available} onClick={() => setSlot(item.id)}>
                    {item.name}
                    <span className={cn('tabular-nums', slot === item.id ? 'text-azure-100' : 'text-steel-500')}>
                      {item.starts_at}–{item.ends_at}
                    </span>
                  </SlotPill>
                ))}
              </div>
              {errors.slot_id && <p className="mt-2 text-sm text-onair-600">{errors.slot_id}</p>}
            </Step>

            <Step index={2} title={t('Qaysi guruh?')} hint={t('Band guruhlarda shu vaqtda boshqa dars bor — ular tanlanmaydi.')}>
              {courses.length > 1 && (
                <Segmented
                  className="mb-4"
                  label={t('Kurs')}
                  value={course}
                  onChange={(value) => {
                    setCourse(value)
                    setGroupId(null)
                  }}
                  options={courses.map((value) => ({ value: String(value), label: t('{n}-kurs', { n: value }) }))}
                />
              )}
              <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" role="radiogroup" aria-label={t('Guruh')}>
                {groups.map((group) => {
                  const active = group.id === groupId
                  return (
                    <button
                      key={group.id}
                      type="button"
                      role="radio"
                      aria-checked={active}
                      disabled={group.busy}
                      onClick={() => setGroupId(group.id)}
                      className={cn(
                        'flex min-h-16 items-start gap-3 rounded-field p-3.5 text-left ring-1 transition-colors ring-inset',
                        active ? 'bg-azure-50 ring-2 ring-azure-500' : 'bg-white ring-steel-200 hover:ring-steel-300',
                        group.busy && 'cursor-not-allowed bg-steel-50 opacity-70 hover:ring-steel-200',
                      )}
                    >
                      <span
                        className={cn(
                          'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full ring-1 ring-inset',
                          active ? 'bg-azure-500 text-white ring-azure-500' : 'ring-steel-300',
                        )}
                        aria-hidden
                      >
                        {active && <Check className="size-3.5" strokeWidth={3} />}
                      </span>
                      <span className="min-w-0">
                        <span className="block text-[0.9375rem] leading-snug font-medium text-ink-900">{group.name}</span>
                        <span className={cn('mt-1 block text-sm', group.busy ? 'text-onair-600' : 'text-leaf-600')}>
                          {group.busy ? (group.busy_until ? t('Band, {time} gacha', { time: formatTime(group.busy_until) }) : t('Band')) : t('Boʻsh')}
                        </span>
                      </span>
                    </button>
                  )
                })}
              </div>
              {errors.group_id && <p className="mt-2 text-sm text-onair-600">{errors.group_id}</p>}
            </Step>

            <Step index={3} title={t('Mavzu')} hint={t('Ixtiyoriy. Koʻrsatilmasa, guruh nomi ishlatiladi.')}>
              <Textarea
                aria-label={t('Dars mavzusi')}
                value={topic}
                maxLength={200}
                onChange={(event) => setTopic(event.target.value)}
                placeholder={t('Masalan: Chiziqli algebra, 5-mavzu')}
                error={errors.topic}
              />
            </Step>
          </div>

          <aside className="lg:sticky lg:top-10 lg:self-start">
            <div className="rounded-panel bg-white p-6 ring-1 ring-steel-200/70">
              <h2 className="text-base font-semibold">{t('Yaratiladigan dars')}</h2>
              <dl className="mt-5 space-y-4 text-sm">
                <div>
                  <dt className="text-steel-600">{t('Guruh')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900">{selectedGroup?.name ?? t('Tanlanmagan')}</dd>
                </div>
                <div>
                  <dt className="text-steel-600">{t('Vaqt')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900 tabular-nums">
                    {selectedSlot ? `${selectedSlot.name}, ${selectedSlot.starts_at}–${selectedSlot.ends_at}` : t('Hozirdan boshlab')}
                  </dd>
                </div>
                <div>
                  <dt className="text-steel-600">{t('Davomiyligi')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900">{formatDuration(duration)}</dd>
                </div>
              </dl>
              <Button type="submit" size="lg" className="mt-6 w-full" disabled={!groupId} loading={create.isPending}>
                {t('Darsni yaratish')}
              </Button>
              {!groupId && <p className="mt-3 text-center text-sm text-steel-500">{t('Avval guruhni tanlang')}</p>}
            </div>
          </aside>
        </form>
      )}

      <CreatedModal
        lesson={created}
        onClose={() => {
          setCreated(null)
          navigate('/')
        }}
      />
    </>
  )
}

function SlotPill({ active, disabled, onClick, children }: { active: boolean; disabled?: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={active}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium transition-colors',
        active ? 'bg-azure-500 text-white' : 'bg-white text-ink-800 ring-1 ring-steel-200 ring-inset hover:ring-steel-300',
        'disabled:cursor-not-allowed disabled:bg-steel-50 disabled:text-steel-400 disabled:ring-steel-100',
      )}
    >
      {children}
    </button>
  )
}
