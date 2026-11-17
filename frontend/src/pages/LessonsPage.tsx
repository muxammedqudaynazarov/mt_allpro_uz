import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { CalendarPlus, CalendarX2, Download } from 'lucide-react'
import { useAuth } from '@/auth/AuthProvider'
import { api, cleanParams } from '@/lib/api'
import type { Group, Lesson, Paginated } from '@/lib/types'
import { buttonClass } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input, Select } from '@/components/ui/Field'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Pagination } from '@/components/ui/Pagination'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { LessonRow } from '@/features/lessons/LessonRow'
import { useT } from '@/i18n/I18nProvider'

type StatusFilter = 'all' | 'live' | 'scheduled' | 'finished'

export default function LessonsPage() {
  const t = useT()
  const { canViewAll, can } = useAuth()
  const [params, setParams] = useSearchParams()
  const [page, setPage] = useState(1)
  const status = (params.get('status') as StatusFilter) || 'all'
  const filters = {
    status: status === 'all' ? '' : status,
    group_id: params.get('group_id') ?? '',
    from: params.get('from') ?? '',
    to: params.get('to') ?? '',
    mine: params.get('mine') ?? '',
  }

  function update(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    setParams(next, { replace: true })
    setPage(1)
  }

  const lessons = useQuery({
    queryKey: ['lessons', filters, page],
    queryFn: async () => (await api.get<Paginated<Lesson>>('/api/lessons', { params: cleanParams({ ...filters, page }) })).data,
    placeholderData: keepPreviousData,
    refetchInterval: 30_000,
  })

  const groups = useQuery({
    queryKey: ['groups', 'all'],
    queryFn: async () => (await api.get<{ data: Group[] }>('/api/groups')).data.data,
    enabled: canViewAll,
  })

  const exportUrl = `/api/lessons/export?${new URLSearchParams(cleanParams({ ...filters }) as Record<string, string>).toString()}`

  return (
    <>
      <PageHeader
        title={t('Darslar')}
        description={canViewAll ? t('Barcha guruhlardagi darslar.') : t('Siz yaratgan darslar tarixi.')}
        actions={
          <>
            {canViewAll && (
              <a href={exportUrl} className={buttonClass({ variant: 'secondary' })}>
                <Download className="size-4" />
                Excel (.xlsx)
              </a>
            )}
            {can('lessons.create') && (
              <Link to="/lessons/new" className={buttonClass()}>
                <CalendarPlus className="size-4" />
                {t('Dars yaratish')}
              </Link>
            )}
          </>
        }
      />

      <div className="mb-5 space-y-3">
        <Segmented<StatusFilter>
          label={t('Holat')}
          value={status}
          onChange={(value) => update('status', value === 'all' ? '' : value)}
          options={[
            { value: 'all', label: t('Barchasi') },
            { value: 'live', label: t('Efirda') },
            { value: 'scheduled', label: t('Rejalashtirilgan') },
            { value: 'finished', label: t('Yakunlangan') },
          ]}
        />
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_10.5rem_10.5rem]">
          {canViewAll && (
            <Select aria-label={t('Guruh')} wrapperClassName="col-span-2 lg:col-span-1" value={filters.group_id} onChange={(event) => update('group_id', event.target.value)}>
              <option value="">{t('Barcha guruhlar')}</option>
              {groups.data?.map((group) => (
                <option key={group.id} value={group.id}>
                  {group.name}
                </option>
              ))}
            </Select>
          )}
          {canViewAll && (
            <Select aria-label={t('Kimniki')} wrapperClassName="col-span-2 lg:col-span-1" value={filters.mine} onChange={(event) => update('mine', event.target.value)}>
              <option value="">{t('Hamma oʻqituvchilar')}</option>
              <option value="1">{t('Faqat meniki')}</option>
            </Select>
          )}
          <Input type="date" aria-label={t('Sanadan')} title={t('Sanadan')} value={filters.from} max={filters.to || undefined} onChange={(event) => update('from', event.target.value)} />
          <Input type="date" aria-label={t('Sanagacha')} title={t('Sanagacha')} value={filters.to} min={filters.from || undefined} onChange={(event) => update('to', event.target.value)} />
        </div>
      </div>

      <Panel padded={false}>
        {lessons.isPending ? (
          <SectionLoader />
        ) : !lessons.data?.data.length ? (
          <EmptyState
            icon={<CalendarX2 className="size-5" />}
            title={t('Darslar topilmadi')}
            description={t('Filtrlarni oʻzgartirib koʻring yoki yangi dars yarating.')}
          />
        ) : (
          <>
            <ul className="divide-y divide-steel-100">
              {lessons.data.data.map((lesson) => (
                <LessonRow key={lesson.id} lesson={lesson} />
              ))}
            </ul>
            <Pagination page={lessons.data.meta.current_page} lastPage={lessons.data.meta.last_page} total={lessons.data.meta.total} onChange={setPage} />
          </>
        )}
      </Panel>
    </>
  )
}
