import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { useMutation, useQuery } from '@tanstack/react-query'
import { AlertTriangle, Check, Copy, Play, Send, Sparkles, X } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { addDays, formatDayMonth, formatDuration, formatTime, formatWeekday, isoDate, isoWeekday } from '@/lib/format'
import type { Lesson, LessonOptionHost, LessonOptions } from '@/lib/types'
import { Button, buttonClass } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader } from '@/components/ui/Page'
import { Segmented } from '@/components/ui/Segmented'
import { Switch } from '@/components/ui/Switch'
import { SectionLoader } from '@/components/ui/Spinner'
import { cn } from '@/components/ui/cn'
import { copyText, telegramShareUrl, useLessonActions } from '@/features/lessons/useLessonActions'
import { groupsLabel, MAX_LECTURE_GROUPS } from '@/lib/groups'
import { useT } from '@/i18n/I18nProvider'

/** Faqat jadvaldagi juftlik: oldindan rejalashtirilmagan ("hozir") dars yaratilmaydi. */
type SlotChoice = number | null
type HostChoice = 'auto' | number

/** Kalendar huquqi bo'lsa — bugundan boshlab 14 kun ichida rejalashtirish mumkin. */
const PLAN_DAYS = 14

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
      description={`${lesson.is_lecture ? `${t('Maʼruza')}: ` : ''}${groupsLabel(lesson)}, ${formatTime(lesson.starts_at)}–${formatTime(lesson.ends_at)}`}
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
  const { can, config } = useAuth()
  const [params] = useSearchParams()
  const today = isoDate()
  const canPlan = can('calendar.manage')
  const closedWeekdays = useMemo(() => config?.closed_weekdays ?? [7], [config])
  // Dam olish kunlari (yakshanba) tanlovda ko'rinmaydi.
  const days = useMemo(
    () =>
      Array.from({ length: canPlan ? PLAN_DAYS + 7 : 1 }, (_, index) => isoDate(addDays(new Date(), index)))
        .filter((day) => !closedWeekdays.includes(isoWeekday(day)))
        .slice(0, canPlan ? PLAN_DAYS : 1),
    [canPlan, closedWeekdays],
  )
  const initialDate = params.get('date')
  const [date, setDate] = useState<string>(initialDate && days.includes(initialDate) ? initialDate : (days[0] ?? today))
  const [slot, setSlot] = useState<SlotChoice>(params.get('slot') ? Number(params.get('slot')) : null)
  const [course, setCourse] = useState<string>('')
  // Ma'ruzada bir nechta guruh (tanlangan tartibda, birinchisi — asosiy), oddiy darsda — bitta.
  const [lecture, setLecture] = useState(false)
  const [groupIds, setGroupIds] = useState<number[]>([])
  const groupId = groupIds[0] ?? null
  const [host, setHost] = useState<HostChoice>('auto')
  const [topic, setTopic] = useState('')
  const [repeat, setRepeat] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [created, setCreated] = useState<Lesson | null>(null)
  const isToday = date === today

  const options = useQuery({
    queryKey: ['lesson-options', date, slot, groupId],
    queryFn: async () =>
      (await api.get<{ data: LessonOptions }>('/api/lessons/options', { params: { slot_id: slot ?? undefined, date, group_id: groupId ?? undefined } })).data.data,
    placeholderData: (previous) => previous,
    refetchInterval: 30_000,
  })

  useEffect(() => {
    // Tanlanmagan yoki o'tib ketgan juftlik o'rniga birinchi mavjud juftlik tanlanadi.
    const slots = options.data?.slots
    if (!slots?.length) return
    const current = slots.find((item) => item.id === slot)
    if (!current || !current.available) setSlot(slots.find((item) => item.available)?.id ?? null)
  }, [slot, options.data?.slots])

  const courses = useMemo(() => {
    const set = new Set(options.data?.groups.map((group) => group.course))
    return [...set].sort((a, b) => a - b)
  }, [options.data])

  useEffect(() => {
    if (!course && courses.length) setCourse(String(courses[0]))
  }, [course, courses])

  const groups = options.data?.groups.filter((group) => String(group.course) === course) ?? []
  const selectedGroups = groupIds.map((id) => options.data?.groups.find((group) => group.id === id)).filter((group) => group !== undefined)
  const selectedSlot = options.data?.slots.find((item) => item.id === slot)
  const busyKey = selectedGroups.filter((group) => group.busy).map((group) => group.id).join(',')

  useEffect(() => {
    // Tanlangan guruh band bo'lib qolsa, tanlovdan chiqaramiz.
    if (busyKey) setGroupIds((current) => current.filter((id) => !busyKey.split(',').includes(String(id))))
  }, [busyKey])

  function toggleGroup(id: number) {
    if (!lecture) return setGroupIds([id])
    setGroupIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : current.length >= MAX_LECTURE_GROUPS ? current : [...current, id]))
  }

  const hosts = options.data?.hosts ?? []
  const selectedHost = host === 'auto' ? undefined : hosts.find((item) => item.id === host)
  const autoHost = hosts.find((item) => !item.busy)

  useEffect(() => {
    // Tanlangan host band bo'lib qolsa yoki ro'yxatdan chiqsa — avtomatik tanlovga qaytamiz.
    if (host !== 'auto' && (!selectedHost || selectedHost.busy)) setHost('auto')
  }, [host, selectedHost])

  const create = useMutation({
    mutationFn: async () =>
      (
        await api.post<{ data: Lesson }>('/api/lessons', {
          ...(lecture ? { is_lecture: true, group_ids: groupIds } : { group_id: groupId }),
          slot_id: slot,
          host_id: host === 'auto' ? null : host,
          date: isToday ? null : date,
          topic: topic.trim() || null,
          repeat_weekly: canPlan && repeat,
        })
      ).data.data,
    onSuccess: (lesson) => {
      setCreated(lesson)
      setTopic('')
      setGroupIds([])
      setHost('auto')
      setRepeat(false)
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

  // Zoom'ni avtomatik yakunlash yoqilgan bo'lsa — dars juftlikning bir qismi (server hisoblagan oraliq).
  const windowMinutes = options.data && selectedSlot?.available ? Math.round((new Date(options.data.to).getTime() - new Date(options.data.from).getTime()) / 60_000) : null
  const duration = windowMinutes ?? selectedSlot?.duration_minutes ?? options.data?.default_duration ?? 80
  const groupError = errors.group_id ?? errors.group_ids ?? Object.entries(errors).find(([key]) => key.startsWith('group_ids.'))?.[1]
  const shownHost = selectedHost ?? autoHost

  return (
    <>
      <PageHeader
        title={t('Dars yaratish')}
        description={t('Vaqt, guruh va boʻsh Zoom hostni tanlang — uchrashuv avtomatik yaratiladi va havola sizga yuboriladi.')}
      />

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
            <Step
              index={1}
              title={t('Qachon?')}
              hint={canPlan ? t('Kunni va juftlikni tanlang. Dars faqat jadvaldagi juftlikka yaratiladi.') : t('Bugungi juftliklardan birini tanlang.')}
            >
              {canPlan && (
                <div className="-mx-1 mb-4 flex gap-1.5 overflow-x-auto px-1 pb-1" role="radiogroup" aria-label={t('Kun')}>
                  {days.map((day) => {
                    const active = day === date
                    const value = new Date(`${day}T12:00:00+05:00`)
                    return (
                      <button
                        key={day}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        onClick={() => {
                          setDate(day)
                          setGroupIds([])
                        }}
                        className={cn(
                          'flex w-[4.5rem] shrink-0 flex-col items-center rounded-field px-2 py-2 text-center ring-1 transition-colors ring-inset',
                          active ? 'bg-ink-900 text-white ring-ink-900' : 'bg-white text-ink-800 ring-steel-200 hover:ring-steel-300',
                        )}
                      >
                        <span className={cn('text-xs capitalize', active ? 'text-steel-200' : 'text-steel-500')}>
                          {day === today ? t('Bugun') : formatWeekday(value).slice(0, 3)}
                        </span>
                        <span className="mt-0.5 text-sm font-semibold tabular-nums">{formatDayMonth(value).split('-')[0]}</span>
                        <span className={cn('text-[0.6875rem]', active ? 'text-steel-300' : 'text-steel-500')}>{formatDayMonth(value).split('-')[1]}</span>
                      </button>
                    )
                  })}
                </div>
              )}
              {errors.date && <p className="mb-2 text-sm text-onair-600">{errors.date}</p>}
              {options.data.closed && <p className="mb-2 text-sm font-medium text-amber-700">{t('Dam olish kuni — bu kunga dars yaratib boʻlmaydi.')}</p>}
              <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={t('Dars vaqti')}>
                {options.data.slots.map((item) => (
                  <SlotPill key={item.id} active={slot === item.id} disabled={!item.available} onClick={() => setSlot(item.id)}>
                    {item.name}
                    <span className={cn('tabular-nums', slot === item.id ? 'text-azure-100' : 'text-steel-500')}>
                      {item.starts_at}–{item.ends_at}
                    </span>
                  </SlotPill>
                ))}
              </div>
              {!options.data.closed && options.data.slots.some((item) => !item.available) && (
                <p className="mt-2 text-sm text-steel-500">
                  {options.data.slots.some((item) => item.available)
                    ? t('Boshlangan juftliklar tanlanmaydi.')
                    : canPlan
                      ? t('Bugungi barcha juftliklar boshlanib boʻlgan. Boshqa kunni tanlang.')
                      : t('Bugungi barcha juftliklar boshlanib boʻlgan.')}
                </p>
              )}
              {errors.slot_id && <p className="mt-2 text-sm text-onair-600">{errors.slot_id}</p>}
            </Step>

            <Step
              index={2}
              title={lecture ? t('Qaysi guruhlar?') : t('Qaysi guruh?')}
              hint={
                lecture
                  ? t('Maʼruzaga qatnashadigan guruhlarni belgilang (koʻpi bilan {max} ta) — hammasi bitta Zoom uchrashuviga kiradi.', { max: MAX_LECTURE_GROUPS })
                  : t('Band guruhlarda shu vaqtda boshqa dars bor — ular tanlanmaydi.')
              }
            >
              <label className="mb-4 flex items-start gap-3 rounded-field bg-mist/70 p-3.5">
                <Switch
                  checked={lecture}
                  onChange={(value) => {
                    setLecture(value)
                    if (!value) setGroupIds((current) => current.slice(0, 1))
                  }}
                  label={t('Maʼruza')}
                />
                <span className="text-sm">
                  <span className="block font-medium text-ink-900">{t('Maʼruza')}</span>
                  <span className="block text-steel-600">{t('Bir nechta guruh bitta darsda birga oʻtadi. Davomat va hisobot har bir guruhga alohida boʻlinadi.')}</span>
                </span>
              </label>
              {courses.length > 1 && (
                <Segmented
                  className="mb-4"
                  label={t('Kurs')}
                  value={course}
                  onChange={(value) => {
                    setCourse(value)
                    // Ma'ruzada boshqa kurs guruhlari ham tanlanishi mumkin — tanlov saqlanadi.
                    if (!lecture) setGroupIds([])
                  }}
                  options={courses.map((value) => ({ value: String(value), label: t('{n}-kurs', { n: value }) }))}
                />
              )}
              <div
                className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                role={lecture ? 'group' : 'radiogroup'}
                aria-label={lecture ? t('Guruhlar') : t('Guruh')}
              >
                {groups.map((group) => {
                  const active = groupIds.includes(group.id)
                  const full = lecture && !active && groupIds.length >= MAX_LECTURE_GROUPS
                  return (
                    <button
                      key={group.id}
                      type="button"
                      role={lecture ? 'checkbox' : 'radio'}
                      aria-checked={active}
                      disabled={group.busy || full}
                      onClick={() => toggleGroup(group.id)}
                      className={cn(
                        'flex min-h-16 items-start gap-3 rounded-field p-3.5 text-left ring-1 transition-colors ring-inset',
                        active ? 'bg-azure-50 ring-2 ring-azure-500' : 'bg-white ring-steel-200 hover:ring-steel-300',
                        group.busy && 'cursor-not-allowed bg-steel-50 opacity-70 hover:ring-steel-200',
                      )}
                    >
                      <span
                        className={cn(
                          'mt-0.5 flex size-5 shrink-0 items-center justify-center ring-1 ring-inset',
                          lecture ? 'rounded-[0.3rem]' : 'rounded-full',
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
              {lecture && selectedGroups.length > 0 && (
                <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                  <span className="text-steel-600">{t('Tanlangan: {count} ta guruh', { count: selectedGroups.length })}</span>
                  {selectedGroups.map((group) => (
                    <button
                      key={group.id}
                      type="button"
                      onClick={() => toggleGroup(group.id)}
                      className="inline-flex h-7 items-center gap-1 rounded-full bg-azure-50 pr-2 pl-3 font-medium text-azure-700 ring-1 ring-azure-200 ring-inset hover:bg-azure-100"
                      aria-label={t('{group} guruhini olib tashlash', { group: group.name })}
                    >
                      {group.name}
                      <X className="size-3.5" aria-hidden />
                    </button>
                  ))}
                </div>
              )}
              {groupError && <p className="mt-2 text-sm text-onair-600">{groupError}</p>}
            </Step>

            <Step index={3} title={t('Zoom host')} hint={t('Bir host bir vaqtda faqat bitta dars oʻtkaza oladi. Band hostlar tanlanmaydi.')}>
              <HostPicker hosts={hosts} value={host} onChange={setHost} autoHost={autoHost} />
              {errors.host_id && <p className="mt-2 text-sm text-onair-600">{errors.host_id}</p>}
            </Step>

            <Step
              index={4}
              title={t('Mavzu')}
              hint={lecture ? t('Ixtiyoriy. Koʻrsatilmasa, “Maʼruza” va guruhlar nomi ishlatiladi.') : t('Ixtiyoriy. Koʻrsatilmasa, guruh nomi ishlatiladi.')}
            >
              <Textarea
                aria-label={t('Dars mavzusi')}
                value={topic}
                maxLength={200}
                onChange={(event) => setTopic(event.target.value)}
                placeholder={t('Masalan: Chiziqli algebra, 5-mavzu')}
                error={errors.topic}
              />
              {canPlan && (
                <label className="mt-5 flex items-start gap-3">
                  <Switch checked={repeat} onChange={setRepeat} label={t('Har hafta takrorlash')} />
                  <span className="text-sm">
                    <span className="block font-medium text-ink-900">
                      {t('Har hafta takrorlash')}
                      {selectedSlot && `: ${formatWeekday(new Date(`${date}T12:00:00+05:00`))}, ${selectedSlot.starts_at}`}
                    </span>
                    <span className="block text-steel-600">
                      {t('Keyingi haftalarda Zoom uchrashuvi dars kuni avtomatik yaratiladi, havolalar darsdan 30 daqiqa oldin Telegram’ga yuboriladi.')}
                    </span>
                  </span>
                </label>
              )}
            </Step>
          </div>

          <aside className="lg:sticky lg:top-10 lg:self-start">
            <div className="rounded-panel bg-white p-6 ring-1 ring-steel-200/70">
              <h2 className="text-base font-semibold">{t('Yaratiladigan dars')}</h2>
              <dl className="mt-5 space-y-4 text-sm">
                <div>
                  <dt className="text-steel-600">{lecture ? t('Maʼruza, guruhlar') : t('Guruh')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900">{selectedGroups.length ? selectedGroups.map((group) => group.name).join(', ') : t('Tanlanmagan')}</dd>
                </div>
                <div>
                  <dt className="text-steel-600">{t('Vaqt')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900 tabular-nums">
                    {!isToday && (
                      <span className="block first-letter:uppercase">
                        {formatWeekday(new Date(`${date}T12:00:00+05:00`))}, {formatDayMonth(new Date(`${date}T12:00:00+05:00`))}
                      </span>
                    )}
                    {selectedSlot ? `${selectedSlot.name}, ${selectedSlot.starts_at}–${selectedSlot.ends_at}` : t('Tanlanmagan')}
                  </dd>
                </div>
                <div>
                  <dt className="text-steel-600">{t('Zoom host')}</dt>
                  <dd className="mt-0.5 font-medium break-all text-ink-900">
                    {shownHost ? shownHost.name : <span className="text-onair-600">{t('Boʻsh host yoʻq')}</span>}
                    {host === 'auto' && shownHost && <span className="block text-xs font-normal text-steel-500">{t('avtomatik tanlanadi')}</span>}
                  </dd>
                </div>
                {repeat && canPlan && (
                  <div>
                    <dt className="text-steel-600">{t('Takrorlanadi')}</dt>
                    <dd className="mt-0.5 font-medium text-ink-900">{t('Har hafta, bekor qilinguncha')}</dd>
                  </div>
                )}
                <div>
                  <dt className="text-steel-600">{t('Davomiyligi')}</dt>
                  <dd className="mt-0.5 font-medium text-ink-900">
                    {formatDuration(duration)}
                    {options.data.auto_end && <span className="block text-xs font-normal text-steel-500">{t('Zoom shu vaqtda avtomatik yakunlanadi')}</span>}
                  </dd>
                </div>
              </dl>
              <Button type="submit" size="lg" className="mt-6 w-full" disabled={!groupId || !shownHost || !slot} loading={create.isPending}>
                {t('Darsni yaratish')}
              </Button>
              {!groupId && <p className="mt-3 text-center text-sm text-steel-500">{lecture ? t('Avval guruhlarni tanlang') : t('Avval guruhni tanlang')}</p>}
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

function HostPicker({
  hosts,
  value,
  onChange,
  autoHost,
}: {
  hosts: LessonOptionHost[]
  value: HostChoice
  onChange: (value: HostChoice) => void
  autoHost: LessonOptionHost | undefined
}) {
  const t = useT()
  const manyAccounts = new Set(hosts.map((item) => item.account)).size > 1
  if (!hosts.length) {
    return <p className="rounded-field bg-onair-50 p-3.5 text-sm text-onair-600">{t('Zoom hostlar qoʻshilmagan. Administrator Zoom akkauntlar sahifasida hostlarni yangilashi kerak.')}</p>
  }
  return (
    <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3" role="radiogroup" aria-label={t('Zoom host')}>
      <HostOption active={value === 'auto'} disabled={!autoHost} onClick={() => onChange('auto')}>
        <span className="flex items-center gap-1.5 text-[0.9375rem] font-medium text-ink-900">
          <Sparkles className="size-4 text-azure-500" />
          {t('Avtomatik')}
        </span>
        <span className={cn('mt-1 block text-sm', autoHost ? 'text-steel-600' : 'text-onair-600')}>
          {autoHost ? t('Birinchi boʻsh host: {name}', { name: autoHost.name }) : t('Bu vaqtda boʻsh host yoʻq')}
        </span>
      </HostOption>
      {hosts.map((item) => (
        <HostOption key={item.id} active={value === item.id} disabled={item.busy} onClick={() => onChange(item.id)}>
          <span className="block text-[0.9375rem] leading-snug font-medium break-all text-ink-900">{item.name}</span>
          {item.name !== item.email && <span className="block text-xs break-all text-steel-500">{item.email}</span>}
          {manyAccounts && item.account && <span className="block text-xs text-steel-500">{item.account}</span>}
          <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
            <span className={item.busy ? 'text-onair-600' : 'text-leaf-600'}>
              {item.busy ? (item.busy_until ? t('Band, {time} gacha', { time: formatTime(item.busy_until) }) : t('Band')) : t('Boʻsh')}
            </span>
            {item.default && <span className="text-steel-500">{t('guruh standarti')}</span>}
          </span>
          {!item.licensed && (
            <span className="mt-1 flex items-center gap-1 text-xs text-amber-600">
              <AlertTriangle className="size-3.5" />
              {t('Basic: uchrashuv 40 daqiqa bilan cheklangan')}
            </span>
          )}
        </HostOption>
      ))}
    </div>
  )
}

function HostOption({ active, disabled, onClick, children }: { active: boolean; disabled?: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={active}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'flex min-h-16 items-start gap-3 rounded-field p-3.5 text-left ring-1 transition-colors ring-inset',
        active ? 'bg-azure-50 ring-2 ring-azure-500' : 'bg-white ring-steel-200 hover:ring-steel-300',
        disabled && 'cursor-not-allowed bg-steel-50 opacity-70 hover:ring-steel-200',
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
      <span className="min-w-0">{children}</span>
    </button>
  )
}
