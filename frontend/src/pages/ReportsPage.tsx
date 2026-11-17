import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, Download } from 'lucide-react'
import { api } from '@/lib/api'
import { addDays, formatDate, formatDuration, isoDate } from '@/lib/format'
import type { ReportSummary } from '@/lib/types'
import { buttonClass } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

function monthStart(): string {
  const today = isoDate()
  return `${today.slice(0, 8)}01`
}

function Bar({ value, max }: { value: number; max: number }) {
  return (
    <div className="h-1.5 w-full rounded-full bg-steel-100" aria-hidden>
      <div className="h-full rounded-full bg-azure-500" style={{ width: `${max ? Math.max(3, (value / max) * 100) : 0}%` }} />
    </div>
  )
}

export default function ReportsPage() {
  const t = useT()
  const [from, setFrom] = useState(monthStart)
  const [to, setTo] = useState(() => isoDate())

  const report = useQuery({
    queryKey: ['reports', from, to],
    queryFn: async () => (await api.get<{ data: ReportSummary }>('/api/reports/summary', { params: { from, to } })).data.data,
    enabled: !!from && !!to,
  })

  const data = report.data
  const maxTeacher = Math.max(0, ...(data?.by_teacher.map((row) => row.lessons) ?? []))
  const maxGroup = Math.max(0, ...(data?.by_group.map((row) => row.lessons) ?? []))
  const presets = [
    { label: t('Shu oy'), from: monthStart(), to: isoDate() },
    { label: t('Oxirgi 7 kun'), from: isoDate(addDays(new Date(), -6)), to: isoDate() },
    { label: t('Oxirgi 30 kun'), from: isoDate(addDays(new Date(), -29)), to: isoDate() },
  ]

  return (
    <>
      <PageHeader
        title={t('Hisobotlar')}
        description={t('Yakunlangan va davom etayotgan darslar soni hamda davomiyligi.')}
        actions={
          <a href={`/api/lessons/export?from=${from}&to=${to}`} className={buttonClass({ variant: 'secondary' })}>
            <Download className="size-4" />
            Batafsil roʻyxat (CSV)
          </a>
        }
      />

      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">
        <Input type="date" label={t('Sanadan')} wrapperClassName="sm:w-44" value={from} max={to} onChange={(e) => setFrom(e.target.value)} />
        <Input type="date" label={t('Sanagacha')} wrapperClassName="sm:w-44" value={to} min={from} onChange={(e) => setTo(e.target.value)} />
        <div className="flex flex-wrap gap-2 sm:pb-0.5">
          {presets.map((preset) => (
            <button
              key={preset.label}
              type="button"
              onClick={() => {
                setFrom(preset.from)
                setTo(preset.to)
              }}
              className="h-10 rounded-full px-3.5 text-sm text-steel-700 ring-1 ring-steel-200 ring-inset hover:bg-white hover:text-ink-900"
            >
              {preset.label}
            </button>
          ))}
        </div>
      </div>

      {report.isPending ? (
        <SectionLoader />
      ) : !data || !data.totals.lessons ? (
        <EmptyState className="rounded-panel bg-white ring-1 ring-steel-200" icon={<BarChart3 className="size-5" />} title={t('Bu davrda darslar yoʻq')} description={t('Boshqa sana oraligʻini tanlang.')} />
      ) : (
        <>
          <dl className="mb-6 grid grid-cols-2 overflow-hidden rounded-panel bg-white ring-1 ring-steel-200/70 lg:grid-cols-4">
            {[
              { label: t('Darslar'), value: data.totals.lessons.toLocaleString('ru-RU') },
              { label: t('Umumiy vaqt'), value: formatDuration(data.totals.minutes) },
              { label: t('Oʻqituvchilar'), value: data.totals.teachers },
              { label: t('Guruhlar'), value: data.totals.groups },
            ].map((item, index) => (
              <div key={item.label} className={`px-5 py-5 ${index % 2 ? 'border-l' : ''} ${index > 1 ? 'border-t lg:border-t-0' : ''} ${index === 2 ? 'lg:border-l' : ''} border-steel-100`}>
                <dt className="text-sm text-steel-600">{item.label}</dt>
                <dd className="mt-1 font-display text-xl font-semibold tabular-nums">{item.value}</dd>
              </div>
            ))}
          </dl>
          <p className="mb-4 text-sm text-steel-600">
            {formatDate(data.from)} — {formatDate(data.to)}
          </p>
          <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <Panel title={t('Oʻqituvchilar boʻyicha')} padded={false}>
              <table className="w-full text-sm">
                <thead className="sr-only">
                  <tr>
                    <th>{t('Oʻqituvchi')}</th>
                    <th>{t('Darslar')}</th>
                    <th>{t('Vaqt')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-steel-100">
                  {data.by_teacher.map((row) => (
                    <tr key={row.teacher.id}>
                      <td className="w-1/2 px-5 py-3">
                        <p className="font-medium">{row.teacher.name}</p>
                        <div className="mt-2">
                          <Bar value={row.lessons} max={maxTeacher} />
                        </div>
                      </td>
                      <td className="px-3 py-3 text-right font-semibold tabular-nums">{row.lessons}</td>
                      <td className="px-5 py-3 text-right whitespace-nowrap text-steel-600">{formatDuration(row.minutes)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Panel>
            <Panel title={t('Guruhlar boʻyicha')} padded={false}>
              <table className="w-full text-sm">
                <thead className="sr-only">
                  <tr>
                    <th>{t('Guruh')}</th>
                    <th>{t('Darslar')}</th>
                    <th>{t('Vaqt')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-steel-100">
                  {data.by_group.map((row) => (
                    <tr key={row.group.id}>
                      <td className="w-1/2 px-5 py-3">
                        <p className="font-medium">{row.group.name}</p>
                        <div className="mt-2">
                          <Bar value={row.lessons} max={maxGroup} />
                        </div>
                      </td>
                      <td className="px-3 py-3 text-right font-semibold tabular-nums">{row.lessons}</td>
                      <td className="px-5 py-3 text-right whitespace-nowrap text-steel-600">{formatDuration(row.minutes)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </Panel>
          </div>
        </>
      )}
    </>
  )
}
