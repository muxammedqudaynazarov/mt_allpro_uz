import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'
import { api } from '@/lib/api'
import { formatDate, formatDuration, formatTime, isSameDay } from '@/lib/format'
import type { UserVisits } from '@/lib/types'

/** Kirish tarixi: qachon kirgan va chiqqan, qancha vaqt, qaysi qurilmadan. */
export function UserVisitsModal({ userId, name, onClose }: { userId: number; name: string; onClose: () => void }) {
  const t = useT()
  const [page, setPage] = useState(1)
  const query = useQuery({
    queryKey: ['users', userId, 'visits', page],
    queryFn: async () => (await api.get<UserVisits>(`/api/users/${userId}/visits`, { params: { page } })).data,
    placeholderData: keepPreviousData,
    refetchInterval: 60_000,
  })
  const visits = query.data?.data ?? []

  return (
    <Modal open onClose={onClose} title={t('Kirish tarixi')} description={name} size="lg">
      {!query.data ? (
        <SectionLoader />
      ) : visits.length === 0 ? (
        <p className="py-6 text-center text-sm text-steel-600">{t('Hali tizimga kirmagan.')}</p>
      ) : (
        <>
          <ul className="-mx-6 divide-y divide-steel-100 border-y border-steel-100">
            {visits.map((visit, index) => {
              const newDay = index === 0 || !isSameDay(visit.started_at, visits[index - 1].started_at)
              return (
                <li key={visit.id}>
                  {newDay && (
                    <p className="bg-mist/70 px-6 py-1.5 text-xs font-medium text-steel-600">
                      {isSameDay(visit.started_at, new Date()) ? t('Bugun') : formatDate(visit.started_at)}
                    </p>
                  )}
                  <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-2.5 text-sm">
                    <div className="min-w-0">
                      <p className="font-medium text-ink-900 tabular-nums">
                        {formatTime(visit.started_at)} – {visit.ended_at ? formatTime(visit.ended_at) : t('hozir')}
                        <span className="ml-2 font-normal text-steel-600">{formatDuration(visit.minutes)}</span>
                      </p>
                      <p className="truncate text-xs text-steel-500">{[visit.device, visit.ip].filter(Boolean).join(', ') || '—'}</p>
                    </div>
                    {visit.online ? (
                      <Badge tone="leaf" dot>
                        {t('Onlayn')}
                      </Badge>
                    ) : visit.end_reason === 'logout' ? (
                      <Badge>{t('Chiqdi')}</Badge>
                    ) : (
                      <Badge className="bg-white text-steel-600 ring-1 ring-steel-200 ring-inset">{t('Sahifani yopgan')}</Badge>
                    )}
                  </div>
                </li>
              )
            })}
          </ul>
          {query.data.meta.last_page > 1 && (
            <div className="-mx-6 -mb-2">
              <Pagination page={query.data.meta.current_page} lastPage={query.data.meta.last_page} total={query.data.meta.total} onChange={setPage} />
            </div>
          )}
          <p className="mt-3 text-xs text-steel-500">
            {t('“Sahifani yopgan” — chiqish tugmasini bosmagan: sahifani yopgan yoki 15 daqiqadan koʻp faol boʻlmagan. Tarix 180 kun saqlanadi.')}
          </p>
        </>
      )}
    </Modal>
  )
}
