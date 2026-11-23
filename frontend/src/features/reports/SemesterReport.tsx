import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Download, GraduationCap } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { withBase } from '@/lib/base'
import { api, errorMessage } from '@/lib/api'
import { dayDate, formatDate, formatDuration } from '@/lib/format'
import type { AssessmentCriteria, AssessmentLevel, Semester, SemesterGroupReport, SemesterOverview, SemesterTotals } from '@/lib/types'
import { buttonClass } from '@/components/ui/Button'
import { cn } from '@/components/ui/cn'
import { EmptyState } from '@/components/ui/EmptyState'
import { Select } from '@/components/ui/Field'
import { Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

/** Yomondan yaxshiga. */
export const LEVELS: AssessmentLevel[] = ['past', 'sust', 'qoniqarli', 'ortacha', 'yaxshi', 'alo']

export const LEVEL_LABELS: Record<AssessmentLevel, string> = {
  past: 'Qoniqarsiz',
  sust: 'Sust',
  qoniqarli: 'Qoniqarli',
  ortacha: 'Oʻrtacha',
  yaxshi: 'Yaxshi',
  alo: 'Aʼlo',
}

const LEVEL_BAR: Record<AssessmentLevel, string> = {
  past: 'bg-onair-500',
  sust: 'bg-amber-500',
  qoniqarli: 'bg-steel-400',
  ortacha: 'bg-azure-300',
  yaxshi: 'bg-azure-500',
  alo: 'bg-leaf-500',
}

const LEVEL_TEXT: Record<AssessmentLevel, string> = {
  past: 'bg-onair-50 text-onair-600',
  sust: 'bg-amber-50 text-amber-600',
  qoniqarli: 'bg-steel-100 text-steel-700',
  ortacha: 'bg-azure-50 text-azure-600',
  yaxshi: 'bg-azure-50 text-azure-800',
  alo: 'bg-leaf-50 text-leaf-600',
}

export function LevelBadge({ level }: { level: AssessmentLevel | null }) {
  const t = useT()
  if (!level) return <span className="text-steel-400">—</span>
  return <span className={cn('inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', LEVEL_TEXT[level])}>{t(LEVEL_LABELS[level])}</span>
}

/** Darajalar taqsimoti: bitta gorizontal chiziq, uzunligi talabalar soniga mos. */
function LevelBar({ levels, className }: { levels: Record<AssessmentLevel, number>; className?: string }) {
  const t = useT()
  const total = LEVELS.reduce((sum, level) => sum + levels[level], 0)
  if (!total) return <span className="text-sm text-steel-400">—</span>
  const label = LEVELS.filter((level) => levels[level])
    .map((level) => `${t(LEVEL_LABELS[level])}: ${levels[level]}`)
    .join(', ')
  return (
    <div className={cn('flex h-2.5 overflow-hidden rounded-full bg-steel-100', className ?? 'w-full')} role="img" aria-label={label} title={label}>
      {LEVELS.map((level) => (levels[level] ? <span key={level} className={LEVEL_BAR[level]} style={{ width: `${(levels[level] / total) * 100}%` }} /> : null))}
    </div>
  )
}

function CriteriaNote({ criteria }: { criteria: AssessmentCriteria }) {
  const t = useT()
  const { isSuperAdmin } = useAuth()
  const th = criteria.thresholds
  return (
    <p className="text-sm text-steel-600">
      {t('Oʻzlashtirish taxmini faqat Zoom’dagi qatnashuv asosida hisoblanadi, baho emas.')}{' '}
      {criteria.basis === 'time' ? t('Asos: jami qatnashgan vaqt foizi.') : t('Asos: qatnashgan darslar foizi.')}{' '}
      {t('Chegaralar: Sust {sust}%, Qoniqarli {qoniqarli}%, Oʻrtacha {ortacha}%, Yaxshi {yaxshi}%, Aʼlo {alo}%.', th)}{' '}
      {isSuperAdmin && (
        <Link to="/settings" className="text-azure-600 underline-offset-4 hover:underline">
          {t('Mezonni oʻzgartirish')}
        </Link>
      )}
    </p>
  )
}

function Facts({ totals }: { totals: SemesterTotals }) {
  const t = useT()
  const items = [
    {
      label: t('Oʻtkazilgan darslar'),
      value: String(totals.held),
      note: [
        totals.held_lectures > 0 ? t('Maʼruza {lectures}, amaliy {practice}', { lectures: totals.held_lectures, practice: totals.held_practice }) : null,
        totals.missed ? t('{count} tasi oʻtkazilmagan', { count: totals.missed }) : null,
      ]
        .filter(Boolean)
        .join('. '),
    },
    {
      label: t('Zoom’dagi umumiy vaqt'),
      value: formatDuration(totals.zoom_minutes),
      note: totals.avg_lesson_minutes ? t('Dars oʻrtacha {time}', { time: formatDuration(totals.avg_lesson_minutes) }) : null,
    },
    {
      label: t('Dars boshiga qatnashuv'),
      value: totals.avg_students_per_lesson !== null ? t('{count} talaba', { count: totals.avg_students_per_lesson }) : '—',
      note: totals.avg_attendance_percent !== null ? t('Guruhning {percent}%', { percent: totals.avg_attendance_percent }) : null,
    },
    { label: t('Talabalar'), value: String(totals.students), note: totals.pending ? t('{count} ta dars hisoboti kutilmoqda', { count: totals.pending }) : null },
  ]
  return (
    <dl className="grid grid-cols-2 overflow-hidden rounded-panel bg-white ring-1 ring-steel-200/70 lg:grid-cols-4">
      {items.map((item, index) => (
        <div key={item.label} className={cn('border-steel-100 px-5 py-5', index % 2 && 'border-l', index > 1 && 'border-t lg:border-t-0', index === 2 && 'lg:border-l')}>
          <dt className="text-sm text-steel-600">{item.label}</dt>
          <dd className="mt-1 font-display text-xl font-semibold tabular-nums">{item.value}</dd>
          {item.note && <dd className="mt-0.5 text-sm text-steel-600">{item.note}</dd>}
        </div>
      ))}
    </dl>
  )
}

/** Ma'ruza yoki amaliy darslarda qatnashgan: "3 / 4" va foiz. */
function SplitCell({ value, held, low }: { value: { attended: number; percent: number | null }; held: number; low: number }) {
  if (held === 0) return <td className="px-3 py-2.5 text-right text-steel-400">—</td>
  return (
    <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
      {value.attended}
      <span className="text-steel-500"> / {held}</span>
      {value.percent !== null && <span className={cn('ml-1.5 text-xs', value.percent < low ? 'text-onair-600' : 'text-steel-500')}>{value.percent}%</span>}
    </td>
  )
}

function GroupDetail({ semester, groupId }: { semester: Semester; groupId: string }) {
  const t = useT()
  const [, setParams] = useSearchParams()
  const query = useQuery({
    queryKey: ['semester-report', semester.id, groupId],
    queryFn: async () => (await api.get<{ data: SemesterGroupReport }>('/api/reports/semester', { params: { semester_id: semester.id, group_id: groupId } })).data.data,
  })
  if (query.isPending) return <SectionLoader />
  if (!query.data) return <EmptyState title={t('Maʼlumotni yuklab boʻlmadi')} description={errorMessage(query.error)} />
  const report = query.data
  const back = () => setParams({ tab: 'semester', semester: String(semester.id) }, { replace: true })
  // Ma'ruzalar bo'lsa — ma'ruza va amaliy darslardagi qatnashuv alohida ustunlarda.
  const split = report.totals.held_lectures > 0

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <button type="button" onClick={back} className="mb-2 inline-flex items-center gap-1.5 text-sm text-steel-600 hover:text-ink-900">
            <ArrowLeft className="size-4" />
            {t('Barcha guruhlar')}
          </button>
          <h2 className="font-display text-xl font-semibold text-ink-900">{report.group.name}</h2>
          <p className="text-sm text-steel-600">
            {semester.name}, {formatDate(dayDate(semester.starts_on))} – {formatDate(dayDate(semester.ends_on))}
          </p>
        </div>
        <a href={withBase(`/api/reports/semester/export?semester_id=${semester.id}&group_id=${report.group.id}`)} className={buttonClass({ variant: 'secondary' })}>
          <Download className="size-4" />
          {t('Excel (.xlsx)')}
        </a>
      </div>

      <Facts totals={report.totals} />

      <Panel padded={false} title={t('Talabalar boʻyicha')} actions={<LevelBar levels={report.levels} className="w-40" />}>
        {report.students.length === 0 ? (
          <EmptyState
            title={t('Talabalar hali aniqlanmagan')}
            description={t('Talabalar Zoom hisobotidagi ismlari boʻyicha aniqlanadi — birinchi dars hisoboti olingach shu yerda koʻrinadi.')}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className={cn('w-full text-left text-sm', split ? 'min-w-[58rem]' : 'min-w-[46rem]')}>
              <thead className="border-b border-steel-100 text-steel-600">
                <tr>
                  <th className="px-5 py-3 font-medium">{t('Talaba')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Qatnashgan darslar')}</th>
                  {split && (
                    <>
                      <th className="px-3 py-3 text-right font-medium">{t('Maʼruza')}</th>
                      <th className="px-3 py-3 text-right font-medium">{t('Amaliy')}</th>
                    </>
                  )}
                  <th className="px-3 py-3 text-right font-medium">{t('Jami vaqt')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Vaqt foizi')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Oʻrtacha kechikish')}</th>
                  <th className="px-5 py-3 font-medium">{t('Oʻzlashtirish taxmini')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {report.students.map((row) => (
                  <tr key={row.id}>
                    <td className="px-5 py-2.5 font-medium text-ink-900">{row.name}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {row.attended}
                      <span className="text-steel-500"> / {report.totals.held}</span>
                    </td>
                    {split && (
                      <>
                        <SplitCell value={row.lectures} held={report.totals.held_lectures} low={report.criteria.thresholds.sust} />
                        <SplitCell value={row.practice} held={report.totals.held_practice} low={report.criteria.thresholds.sust} />
                      </>
                    )}
                    <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">{formatDuration(row.minutes)}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      <span className={cn(row.percent_time !== null && row.percent_time < report.criteria.thresholds.sust && 'text-onair-600')}>
                        {row.percent_time ?? '—'}
                        {row.percent_time !== null && '%'}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-right whitespace-nowrap text-steel-600 tabular-nums">{row.avg_late_minutes ? formatDuration(row.avg_late_minutes) : '—'}</td>
                    <td className="px-5 py-2.5">
                      <LevelBadge level={row.level} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="border-t border-steel-100 px-5 py-3">
          <CriteriaNote criteria={report.criteria} />
        </div>
      </Panel>
    </div>
  )
}

export function SemesterReportSection() {
  const t = useT()
  const { isSuperAdmin } = useAuth()
  const [params, setParams] = useSearchParams()
  const semesters = useQuery({
    queryKey: ['semesters'],
    queryFn: async () => (await api.get<{ data: Semester[] }>('/api/semesters')).data.data,
  })
  const list = semesters.data ?? []
  const selected = list.find((item) => String(item.id) === params.get('semester')) ?? list.find((item) => item.current) ?? list[0]
  const groupId = params.get('group')

  const overview = useQuery({
    queryKey: ['semester-overview', selected?.id],
    queryFn: async () => (await api.get<{ data: SemesterOverview }>('/api/reports/semester', { params: { semester_id: selected?.id } })).data.data,
    enabled: !!selected && !groupId,
  })

  if (semesters.isPending) return <SectionLoader />
  if (!selected) {
    return (
      <EmptyState
        className="rounded-panel bg-white ring-1 ring-steel-200"
        icon={<GraduationCap className="size-5" />}
        title={t('Semestrlar belgilanmagan')}
        description={
          isSuperAdmin ? t('Oʻquv yilini va uning semestrlari sanalarini kiriting — hisobot shu sanalar boʻyicha tuziladi.') : t('Administrator oʻquv yilini qoʻshishi kerak.')
        }
        action={
          isSuperAdmin ? (
            <Link to="/academic-years" className={buttonClass()}>
              {t('Oʻquv yillari')}
            </Link>
          ) : undefined
        }
      />
    )
  }

  const choose = (id: string) => setParams({ tab: 'semester', semester: id }, { replace: true })

  return (
    <div className="space-y-6">
      {!groupId && (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <Select label={t('Semestr')} wrapperClassName="sm:w-80" value={String(selected.id)} onChange={(e) => choose(e.target.value)}>
            {list.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </Select>
          <a href={withBase(`/api/reports/semester/export?semester_id=${selected.id}`)} className={buttonClass({ variant: 'secondary' })}>
            <Download className="size-4" />
            {t('Barcha guruhlar (.xlsx)')}
          </a>
        </div>
      )}

      {groupId ? (
        <GroupDetail semester={selected} groupId={groupId} />
      ) : overview.isPending ? (
        <SectionLoader />
      ) : !overview.data?.groups?.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          title={t('Bu semestrda darslar yoʻq')}
          description={t('Darslar oʻtkazilishi bilan guruhlar shu yerda paydo boʻladi.')}
        />
      ) : (
        <Panel padded={false} title={t('Guruhlar')}>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[46rem] text-left text-sm">
              <thead className="border-b border-steel-100 text-steel-600">
                <tr>
                  <th className="px-5 py-3 font-medium">{t('Guruh')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Darslar')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Zoom vaqti')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Oʻrtacha qatnashuv')}</th>
                  <th className="w-56 px-5 py-3 font-medium">{t('Oʻzlashtirish taxmini')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {overview.data.groups.map((row) => (
                  <tr key={row.group.id} className="hover:bg-mist/50">
                    <td className="px-5 py-3">
                      <button
                        type="button"
                        onClick={() => setParams({ tab: 'semester', semester: String(selected.id), group: String(row.group.id) })}
                        className="text-left font-medium text-ink-900 underline-offset-4 hover:text-azure-700 hover:underline"
                      >
                        {row.group.name}
                      </button>
                      <p className="text-steel-500">{t('{n}-kurs', { n: row.group.course })}</p>
                    </td>
                    <td className="px-3 py-3 text-right tabular-nums">
                      {row.totals.held}
                      {row.totals.held_lectures > 0 && (
                        <span className="block text-xs text-steel-500">{t('shundan maʼruza {count}', { count: row.totals.held_lectures })}</span>
                      )}
                      {row.totals.missed > 0 && <span className="block text-xs text-amber-600">{t('{count} tasi oʻtkazilmagan', { count: row.totals.missed })}</span>}
                    </td>
                    <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">{formatDuration(row.totals.zoom_minutes)}</td>
                    <td className="px-3 py-3 text-right tabular-nums">{row.totals.avg_attendance_percent !== null ? `${row.totals.avg_attendance_percent}%` : '—'}</td>
                    <td className="px-5 py-3">
                      <LevelBar levels={row.levels} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-steel-100 px-5 py-3 text-xs text-steel-600">
            {LEVELS.map((level) => (
              <span key={level} className="inline-flex items-center gap-1.5">
                <span className={cn('size-2.5 rounded-full', LEVEL_BAR[level])} />
                {t(LEVEL_LABELS[level])}
              </span>
            ))}
          </div>
          {overview.data.criteria && (
            <div className="border-t border-steel-100 px-5 py-3">
              <CriteriaNote criteria={overview.data.criteria} />
            </div>
          )}
        </Panel>
      )}
    </div>
  )
}
