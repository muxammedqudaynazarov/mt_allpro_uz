import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Ban, CheckCircle2, Search, Send, ShieldCheck, Trash2, UserRoundCheck, UsersRound } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, cleanParams, errorMessage } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { ROLE_LABELS, USER_STATUS_LABELS } from '@/lib/labels'
import type { Paginated, Role, UserListItem, UserStatus } from '@/lib/types'
import { useDebounced } from '@/hooks/useDebounced'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Pagination } from '@/components/ui/Pagination'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'
import { useT } from '@/i18n/I18nProvider'

type Tab = 'pending' | 'active' | 'blocked' | 'all'
type Counts = Record<Tab, number>

const statusTone: Record<UserStatus, 'amber' | 'leaf' | 'onair'> = { pending: 'amber', active: 'leaf', blocked: 'onair' }

interface UserPatch {
  id: number
  status?: UserStatus
  roles?: Role[]
  hemis_id?: string | null
  last_name?: string
  first_name?: string
  middle_name?: string | null
  can_manage_calendar?: boolean
}

function useUpdateUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...payload }: UserPatch) => (await api.patch(`/api/users/${id}`, payload)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] })
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
}

const ROLE_HINTS: Record<Role, string> = {
  teacher: 'Dars yaratadi va oʻz darslarini boshqaradi.',
  education: 'Barcha darslarni kuzatadi, hisobot va davomatni oladi. Dars yaratmaydi.',
  super_admin: 'Tizimning barcha sozlamalariga toʻliq kirish.',
}

function UserSettingsModal({ user, self, onClose }: { user: UserListItem; self: boolean; onClose: () => void }) {
  const t = useT()
  const [roles, setRoles] = useState<Role[]>(user.roles)
  const [hemisId, setHemisId] = useState<string>(user.hemis_id ?? '')
  const [lastName, setLastName] = useState(user.last_name ?? '')
  const [firstName, setFirstName] = useState(user.first_name ?? '')
  const [middleName, setMiddleName] = useState(user.middle_name ?? '')
  const [calendar, setCalendar] = useState(user.can_manage_calendar)
  const update = useUpdateUser()
  const toggle = (role: Role) => setRoles((current) => (current.includes(role) ? current.filter((item) => item !== role) : [...current, role]))
  const nameChanged = lastName.trim() !== (user.last_name ?? '') || firstName.trim() !== (user.first_name ?? '') || middleName.trim() !== (user.middle_name ?? '')

  function save() {
    const payload: UserPatch = { id: user.id, can_manage_calendar: calendar }
    if (!self) Object.assign(payload, { roles, hemis_id: hemisId.trim() || null })
    if (nameChanged) Object.assign(payload, { last_name: lastName.trim(), first_name: firstName.trim(), middle_name: middleName.trim() || null })
    update.mutate(payload, {
      onSuccess: () => {
        toast.success(t('Saqlandi'))
        onClose()
      },
    })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={t('Foydalanuvchi sozlamalari')}
      description={user.name}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button loading={update.isPending} disabled={!roles.length || (nameChanged && (lastName.trim().length < 2 || firstName.trim().length < 2))} onClick={save}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      {!self && (
        <fieldset>
          <legend className="text-sm font-medium text-ink-800">{t('Rollar')}</legend>
          <div className="mt-3 space-y-2">
            {(['teacher', 'education', 'super_admin'] as Role[]).map((role) => (
              <label key={role} className="flex cursor-pointer items-start gap-3 rounded-field p-3 ring-1 ring-steel-200 ring-inset has-checked:bg-azure-50 has-checked:ring-azure-300">
                <input type="checkbox" className="mt-0.5 size-4 accent-azure-500" checked={roles.includes(role)} onChange={() => toggle(role)} />
                <span>
                  <span className="block text-sm font-medium">{t(ROLE_LABELS[role])}</span>
                  <span className="block text-sm text-steel-600">{t(ROLE_HINTS[role])}</span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>
      )}

      <fieldset className={self ? '' : 'mt-6'}>
        <legend className="text-sm font-medium text-ink-800">{t('Ism-sharif')}</legend>
        <p className="mt-1 text-sm text-steel-600">{t('Vaqtincha tuzatish: foydalanuvchi keyingi safar HEMIS orqali kirganda ism HEMIS’dagi bilan yangilanadi.')}</p>
        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Input label={t('Familiya')} value={lastName} maxLength={80} onChange={(event) => setLastName(event.target.value)} />
          <Input label={t('Ism')} value={firstName} maxLength={80} onChange={(event) => setFirstName(event.target.value)} />
          <Input label={t('Otasining ismi')} value={middleName} maxLength={80} onChange={(event) => setMiddleName(event.target.value)} />
        </div>
      </fieldset>

      {roles.includes('teacher') && (
        <div className="mt-6 flex items-start justify-between gap-4 rounded-field p-3 ring-1 ring-steel-200 ring-inset">
          <span>
            <span className="block text-sm font-medium text-ink-800">{t('Kalendarni boshqarish')}</span>
            <span className="block text-sm text-steel-600">{t('Har haftalik darslarni rejalashtirish va bekor qilish.')}</span>
          </span>
          <Switch checked={calendar} onChange={setCalendar} label={t('Kalendarni boshqarish')} />
        </div>
      )}

      {!self && (
        <Input
          label={t('HEMIS ID')}
          wrapperClassName="mt-6"
          inputMode="numeric"
          value={hemisId}
          onChange={(event) => setHemisId(event.target.value)}
          hint={t('Eski tizimdan koʻchirilgan foydalanuvchini uning HEMIS hisobiga bogʻlash uchun.')}
        />
      )}
    </Modal>
  )
}

export default function UsersPage() {
  const t = useT()
  const { user: me, isSuperAdmin } = useAuth()
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('status') as Tab) || 'all'
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<UserListItem | null>(null)
  const [deleting, setDeleting] = useState<UserListItem | null>(null)
  const debounced = useDebounced(search)
  const update = useUpdateUser()

  const counts = useQuery({
    queryKey: ['users', 'counts'],
    queryFn: async () => (await api.get<{ data: Counts }>('/api/users/counts')).data.data,
  })

  const users = useQuery({
    queryKey: ['users', 'list', tab, debounced, page],
    queryFn: async () =>
      (await api.get<Paginated<UserListItem>>('/api/users', { params: cleanParams({ status: tab === 'all' ? '' : tab, search: debounced, page }) })).data,
    placeholderData: keepPreviousData,
  })

  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/users/${id}`),
    onSuccess: () => {
      toast.success(t('Foydalanuvchi oʻchirildi'))
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['users'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const setStatus = (user: UserListItem, status: UserStatus, message: string) =>
    update.mutate({ id: user.id, status }, { onSuccess: () => toast.success(message) })

  return (
    <>
      <PageHeader
        title={t('Foydalanuvchilar')}
        description={
          isSuperAdmin
            ? t('HEMIS orqali kirgan foydalanuvchilar shu yerda paydo boʻladi. Ularni tasdiqlang, bloklang yoki rolini oʻzgartiring.')
            : t('Tizim foydalanuvchilari. Ularni Administrator boshqaradi.')
        }
      />

      <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <Segmented<Tab>
          label={t('Holat')}
          value={tab}
          onChange={(value) => {
            const next = new URLSearchParams(params)
            if (value === 'all') next.delete('status')
            else next.set('status', value)
            setParams(next, { replace: true })
            setPage(1)
          }}
          options={[
            { value: 'all', label: t('Barchasi'), count: counts.data?.all },
            { value: 'pending', label: t('Tasdiq kutmoqda'), count: counts.data?.pending },
            { value: 'active', label: t('Faol') },
            { value: 'blocked', label: t('Bloklangan') },
          ]}
        />
        <Input
          aria-label={t('Qidirish')}
          placeholder={t('Ism yoki HEMIS ID')}
          leading={<Search className="size-4" />}
          wrapperClassName="lg:w-80"
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
        />
      </div>

      <Panel padded={false}>
        {users.isPending ? (
          <SectionLoader />
        ) : !users.data?.data.length ? (
          <EmptyState
            icon={<UsersRound className="size-5" />}
            title={tab === 'pending' ? t('Tasdiq kutayotgan foydalanuvchilar yoʻq') : t('Foydalanuvchilar topilmadi')}
            description={tab === 'pending' ? t('Yangi foydalanuvchi HEMIS orqali kirganda u shu yerda paydo boʻladi.') : t('Qidiruv soʻzini oʻzgartirib koʻring.')}
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[52rem] text-left text-sm">
                <thead className="border-b border-steel-100 text-steel-600">
                  <tr>
                    <th className="w-[38%] px-5 py-3 font-medium">{t('Foydalanuvchi')}</th>
                    <th className="px-3 py-3 font-medium">{t('Rol')}</th>
                    <th className="px-3 py-3 font-medium">{t('Holat')}</th>
                    <th className="px-3 py-3 font-medium">{t('Oxirgi kirish')}</th>
                    <th className="px-3 py-3 text-right font-medium">{t('Darslar')}</th>
                    <th className="px-5 py-3">
                      <span className="sr-only">{t('Amallar')}</span>
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-steel-100">
                  {users.data.data.map((user) => {
                    const self = user.id === me?.id
                    return (
                      <tr key={user.id} className="hover:bg-steel-50/60">
                        <td className="px-5 py-3">
                          <div className="flex items-center gap-3">
                            <Avatar name={user.name} src={user.picture_url} />
                            <div className="max-w-72 min-w-0">
                              <p className="flex items-center gap-1.5 font-medium text-ink-900">
                                <span className="truncate">{user.name}</span>
                                {user.telegram_linked && <Send className="size-3.5 shrink-0 text-azure-500" aria-label={t('Telegram ulangan')} />}
                              </p>
                              <p className="truncate text-steel-600">
                                {[user.hemis_id && `HEMIS ${user.hemis_id}`, user.department].filter(Boolean).join(', ') || '—'}
                              </p>
                            </div>
                          </div>
                        </td>
                        <td className="px-3 py-3">
                          <div className="flex flex-wrap gap-1">
                            {user.roles.length ? user.roles.map((role) => <Badge key={role} tone={role === 'teacher' ? 'neutral' : 'azure'}>{t(ROLE_LABELS[role])}</Badge>) : <span className="text-steel-500">—</span>}
                          </div>
                        </td>
                        <td className="px-3 py-3">
                          <Badge tone={statusTone[user.status]} dot>
                            {t(USER_STATUS_LABELS[user.status])}
                          </Badge>
                        </td>
                        <td className="px-3 py-3 whitespace-nowrap text-steel-700">{user.last_login_at ? formatDateTime(user.last_login_at) : '—'}</td>
                        <td className="px-3 py-3 text-right tabular-nums">{user.lessons_count}</td>
                        <td className="px-5 py-2 text-right">
                          {!isSuperAdmin ? null : user.status === 'pending' ? (
                            <Button size="sm" icon={<CheckCircle2 className="size-4" />} onClick={() => setStatus(user, 'active', t('Foydalanuvchi tasdiqlandi'))}>
                              {t('Tasdiqlash')}
                            </Button>
                          ) : (
                            (
                              <ActionMenu
                                items={[
                                  {
                                    label: t('Faollashtirish'),
                                    icon: <UserRoundCheck className="size-4" />,
                                    hidden: self || user.status === 'active',
                                    onClick: () => setStatus(user, 'active', t('Foydalanuvchi faollashtirildi')),
                                  },
                                  {
                                    label: t('Bloklash'),
                                    icon: <Ban className="size-4" />,
                                    hidden: self || user.status === 'blocked',
                                    onClick: () => setStatus(user, 'blocked', t('Foydalanuvchi bloklandi')),
                                  },
                                  {
                                    label: t('Rol va sozlamalar'),
                                    icon: <ShieldCheck className="size-4" />,
                                    onClick: () => setEditing(user),
                                  },
                                  {
                                    label: t('Oʻchirish'),
                                    icon: <Trash2 className="size-4" />,
                                    danger: true,
                                    hidden: self,
                                    onClick: () => setDeleting(user),
                                  },
                                ]}
                              />
                            )
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <Pagination page={users.data.meta.current_page} lastPage={users.data.meta.last_page} total={users.data.meta.total} onChange={setPage} />
          </>
        )}
      </Panel>

      {editing && <UserSettingsModal key={editing.id} user={editing} self={editing.id === me?.id} onClose={() => setEditing(null)} />}

      <ConfirmDialog
        open={!!deleting}
        title={t('Foydalanuvchini oʻchirasizmi?')}
        description={t('{name} tizimdan oʻchiriladi va kira olmaydi. Uning darslari tarixi hisobotlarda saqlanib qoladi.', { name: deleting?.name ?? '' })}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}
