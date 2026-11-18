import { useState } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, RotateCcw, Video } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage } from '@/lib/api'
import { formatBytes, formatDateTime } from '@/lib/format'
import type { Paginated, Recording, RecordingStorage } from '@/lib/types'
import { StaffName } from '@/components/ui/StaffName'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Pagination } from '@/components/ui/Pagination'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { useT } from '@/i18n/I18nProvider'

type Filter = 'all' | 'downloaded' | 'pending' | 'failed'

const TYPE_LABELS: Record<string, string> = { MP4: 'Video', M4A: 'Audio', CHAT: 'Chat', TRANSCRIPT: 'Transkript', CC: 'Subtitr' }

export default function RecordingsPage() {
  const t = useT()
  const { isSuperAdmin } = useAuth()
  const queryClient = useQueryClient()
  const [filter, setFilter] = useState<Filter>('all')
  const [page, setPage] = useState(1)
  const query = useQuery({
    queryKey: ['recordings', filter, page],
    queryFn: async () =>
      (await api.get<Paginated<Recording> & { meta: { storage: RecordingStorage | null } }>('/api/recordings', { params: { status: filter === 'all' ? undefined : filter, page } })).data,
    placeholderData: (previous) => previous,
  })
  const retry = useMutation({
    mutationFn: async (id: number) => api.post(`/api/recordings/${id}/retry`),
    onSuccess: () => {
      toast.success(t('Qayta navbatga qoʻyildi'))
      queryClient.invalidateQueries({ queryKey: ['recordings'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const storage = query.data?.meta.storage

  return (
    <>
      <PageHeader title={t('Video yozuvlar')} description={t('Zoom bulutidan serverga koʻchirilgan dars yozuvlari. Video dars sahifasida ijro etiladi.')} />
      {storage && (
        <dl className="mb-6 grid grid-cols-2 overflow-hidden rounded-panel bg-white ring-1 ring-steel-200/70 lg:grid-cols-4">
          {[
            { label: t('Serverda'), value: formatBytes(storage.used_bytes), note: t('{count} ta fayl', { count: storage.files }) },
            { label: t('Boʻsh joy'), value: storage.free_bytes !== null ? formatBytes(storage.free_bytes) : '—' },
            { label: t('Zoom bulutida'), value: formatBytes(storage.zoom_bytes), note: t('hali oʻchirilmagan') },
            { label: t('Navbatda / xato'), value: `${storage.pending} / ${storage.failed}` },
          ].map((item, index) => (
            <div
              key={item.label}
              className={`border-steel-100 px-5 py-5 ${index % 2 ? 'border-l' : ''} ${index > 1 ? 'border-t lg:border-t-0' : ''} ${index === 2 ? 'lg:border-l' : ''}`}
            >
              <dt className="text-sm text-steel-600">{item.label}</dt>
              <dd className="mt-1 font-display text-xl font-semibold tabular-nums">{item.value}</dd>
              {item.note && <dd className="mt-0.5 text-sm text-steel-600">{item.note}</dd>}
            </div>
          ))}
        </dl>
      )}
      <Segmented<Filter>
        className="mb-4"
        label={t('Holat')}
        value={filter}
        onChange={(value) => {
          setFilter(value)
          setPage(1)
        }}
        options={[
          { value: 'all', label: t('Hammasi') },
          { value: 'downloaded', label: t('Yuklangan') },
          { value: 'pending', label: t('Navbatda'), count: storage?.pending },
          { value: 'failed', label: t('Xato'), count: storage?.failed },
        ]}
      />
      {query.isPending ? (
        <SectionLoader />
      ) : !query.data?.data.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          icon={<Video className="size-5" />}
          title={t('Video yozuvlar yoʻq')}
          description={
            isSuperAdmin
              ? t('Umumiy sozlamalarda “Darslarni avtomatik yozib olish”ni yoqing.')
              : t('Administrator darslarni yozib olishni yoqqach, yozuvlar shu yerda paydo boʻladi.')
          }
        />
      ) : (
        <Panel padded={false}>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[44rem] text-left text-sm">
              <thead className="border-b border-steel-100 text-steel-600">
                <tr>
                  <th className="px-5 py-3 font-medium">{t('Dars')}</th>
                  <th className="px-3 py-3 font-medium">{t('Fayl')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Hajmi')}</th>
                  <th className="px-3 py-3 font-medium">{t('Holat')}</th>
                  <th className="px-5 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {query.data.data.map((item) => (
                  <tr key={item.id}>
                    <td className="px-5 py-3">
                      {item.lesson ? (
                        <Link to={`/lessons/${item.lesson.id}`} className="font-medium text-ink-900 hover:text-azure-700 hover:underline">
                          {item.lesson.group}
                        </Link>
                      ) : (
                        <span className="text-steel-500">{t('Dars topilmadi')}</span>
                      )}
                      <p className="text-steel-500">
                        {item.lesson?.teacher && <StaffName name={item.lesson.teacher} />}
                        {item.lesson && `, ${formatDateTime(item.lesson.starts_at)}`}
                      </p>
                    </td>
                    <td className="px-3 py-3">{t(TYPE_LABELS[item.file_type] ?? item.file_type)}</td>
                    <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">{formatBytes(item.size)}</td>
                    <td className="px-3 py-3">
                      {item.status === 'downloaded' ? (
                        <Badge tone="leaf">{t('Yuklangan')}</Badge>
                      ) : item.status === 'failed' ? (
                        <span title={item.error ?? undefined}>
                          <Badge tone="amber">{t('Xato')}</Badge>
                          {item.error && <span className="mt-1 block max-w-xs truncate text-xs text-steel-500">{item.error}</span>}
                        </span>
                      ) : (
                        <Badge tone="azure">{t('Navbatda')}</Badge>
                      )}
                    </td>
                    <td className="px-5 py-3 text-right whitespace-nowrap">
                      {item.url && (
                        <a href={`${item.url}?download=1`} className="inline-flex items-center gap-1.5 text-azure-600 hover:underline">
                          <Download className="size-4" />
                          {t('Yuklab olish')}
                        </a>
                      )}
                      {item.status === 'failed' && isSuperAdmin && (
                        <Button
                          size="sm"
                          variant="secondary"
                          icon={<RotateCcw className="size-4" />}
                          loading={retry.isPending && retry.variables === item.id}
                          onClick={() => retry.mutate(item.id)}
                        >
                          {t('Qayta urinish')}
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {query.data.meta.last_page > 1 && (
            <div className="border-t border-steel-100 px-5 py-3">
              <Pagination page={page} lastPage={query.data.meta.last_page} total={query.data.meta.total} onChange={setPage} />
            </div>
          )}
        </Panel>
      )}
    </>
  )
}
