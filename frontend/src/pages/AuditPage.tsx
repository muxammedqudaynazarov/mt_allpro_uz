import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { ScrollText } from 'lucide-react'
import { api } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { AuditLog, Paginated } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Pagination } from '@/components/ui/Pagination'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

export default function AuditPage() {
  const t = useT()
  const [page, setPage] = useState(1)
  const logs = useQuery({
    queryKey: ['audit', page],
    queryFn: async () => (await api.get<Paginated<AuditLog>>('/api/audit-logs', { params: { page } })).data,
    placeholderData: keepPreviousData,
  })

  return (
    <>
      <PageHeader title={t('Amallar tarixi')} description={t('Kim, qachon va qaysi amalni bajargani. Xavfsizlik tekshiruvi uchun saqlanadi.')} />
      <Panel padded={false}>
        {logs.isPending ? (
          <SectionLoader />
        ) : !logs.data?.data.length ? (
          <EmptyState icon={<ScrollText className="size-5" />} title={t('Hozircha yozuvlar yoʻq')} />
        ) : (
          <>
            <ul className="divide-y divide-steel-100">
              {logs.data.data.map((log) => (
                <li key={log.id} className="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6">
                  <time className="w-36 shrink-0 text-sm text-steel-600 tabular-nums">{formatDateTime(log.created_at)}</time>
                  <div className="min-w-0 flex-1">
                    <p className="text-[0.9375rem] text-ink-900">{log.description}</p>
                    <p className="text-sm text-steel-600">
                      {log.user ? <StaffName name={log.user.name} /> : t('Tizim')}
                      {log.ip && <span className="text-steel-400">, IP {log.ip}</span>}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
            <Pagination page={logs.data.meta.current_page} lastPage={logs.data.meta.last_page} total={logs.data.meta.total} onChange={setPage} />
          </>
        )}
      </Panel>
    </>
  )
}
