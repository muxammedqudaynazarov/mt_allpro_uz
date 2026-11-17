import { useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Search, Trash2, UsersRound } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { COURSES } from '@/lib/labels'
import type { Group, Paginated, UserListItem, ZoomAccount, ZoomUser } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge, OnAirDot } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { Segmented } from '@/components/ui/Segmented'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'
import { useT } from '@/i18n/I18nProvider'

interface GroupForm {
  name: string
  hemis_id: string
  course: string
  zoom_account_id: string
  zoom_user_email: string
  admin_id: string
  is_active: boolean
}

const emptyForm: GroupForm = { name: '', hemis_id: '', course: '1', zoom_account_id: '', zoom_user_email: '', admin_id: '', is_active: true }

function GroupModal({ group, onClose }: { group: Group | 'new'; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const editing = group !== 'new'
  const [form, setForm] = useState<GroupForm>(() =>
    editing
      ? {
          name: group.name,
          hemis_id: group.hemis_id ?? '',
          course: String(group.course),
          zoom_account_id: group.zoom_account ? String(group.zoom_account.id) : '',
          zoom_user_email: group.zoom_user_email,
          admin_id: group.admin ? String(group.admin.id) : '',
          is_active: group.is_active,
        }
      : emptyForm,
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const set = <K extends keyof GroupForm>(key: K, value: GroupForm[K]) => setForm((current) => ({ ...current, [key]: value }))

  const accounts = useQuery({
    queryKey: ['zoom-accounts'],
    queryFn: async () => (await api.get<{ data: ZoomAccount[] }>('/api/zoom-accounts')).data.data,
  })
  const zoomUsers = useQuery({
    queryKey: ['zoom-accounts', form.zoom_account_id, 'users'],
    queryFn: async () => (await api.get<{ data: ZoomUser[] }>(`/api/zoom-accounts/${form.zoom_account_id}/users`)).data.data,
    enabled: !!form.zoom_account_id,
    retry: false,
    staleTime: 5 * 60_000,
  })
  const admins = useQuery({
    queryKey: ['users', 'admins'],
    queryFn: async () => (await api.get<Paginated<UserListItem>>('/api/users', { params: { role: 'admin', per_page: 100 } })).data.data,
  })

  const save = useMutation({
    mutationFn: async () => {
      const payload = {
        ...form,
        hemis_id: form.hemis_id || null,
        course: Number(form.course),
        zoom_account_id: form.zoom_account_id ? Number(form.zoom_account_id) : null,
        admin_id: form.admin_id ? Number(form.admin_id) : null,
      }
      return editing ? api.put(`/api/groups/${group.id}`, payload) : api.post('/api/groups', payload)
    },
    onSuccess: () => {
      toast.success(editing ? t('Guruh saqlandi') : t('Guruh qoʻshildi'))
      queryClient.invalidateQueries({ queryKey: ['groups'] })
      onClose()
    },
    onError: (error) => {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (!Object.keys(fields).length) toast.error(errorMessage(error))
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={editing ? t('Guruhni tahrirlash') : t('Yangi guruh')}
      description={t('Har bir guruh Zoom akkauntidagi bitta foydalanuvchiga (host) biriktiriladi. Bitta host bir vaqtda faqat bitta dars oʻtkaza oladi.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="group-form" loading={save.isPending}>
            {editing ? t('Saqlash') : t('Qoʻshish')}
          </Button>
        </>
      }
    >
      <form id="group-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-6">
        <Input wrapperClassName="sm:col-span-6" label={t('Guruh nomi')} value={form.name} onChange={(e) => set('name', e.target.value)} error={errors.name} required />
        <Input wrapperClassName="sm:col-span-3" label={t('HEMIS ID')} inputMode="numeric" value={form.hemis_id} onChange={(e) => set('hemis_id', e.target.value)} error={errors.hemis_id} hint={t('Ixtiyoriy')} />
        <Select wrapperClassName="sm:col-span-3" label={t('Kurs')} value={form.course} onChange={(e) => set('course', e.target.value)} error={errors.course}>
          {COURSES.map((course) => (
            <option key={course} value={course}>
              {course}-kurs
            </option>
          ))}
        </Select>
        <Select
          wrapperClassName="sm:col-span-3"
          label={t('Zoom akkaunt')}
          value={form.zoom_account_id}
          onChange={(e) => {
            set('zoom_account_id', e.target.value)
            set('zoom_user_email', '')
          }}
          error={errors.zoom_account_id}
          required
        >
          <option value="">{t('Tanlang')}</option>
          {accounts.data?.map((account) => (
            <option key={account.id} value={account.id}>
              {account.name}
            </option>
          ))}
        </Select>
        <div className="sm:col-span-3">
          <Input
            label={t('Zoom foydalanuvchisi (host)')}
            type="email"
            list="zoom-users"
            placeholder="zoom_1@karsu.uz"
            value={form.zoom_user_email}
            onChange={(e) => set('zoom_user_email', e.target.value)}
            error={errors.zoom_user_email}
            hint={zoomUsers.isError ? t('Zoom’dan roʻyxat olinmadi, emailni qoʻlda kiriting.') : zoomUsers.data ? t('{count} ta foydalanuvchi topildi', { count: zoomUsers.data.length }) : undefined}
            required
          />
          <datalist id="zoom-users">
            {zoomUsers.data?.map((user) => (
              <option key={user.id} value={user.email}>
                {user.name}
              </option>
            ))}
          </datalist>
        </div>
        <Select wrapperClassName="sm:col-span-4" label={t('Masʼul administrator')} value={form.admin_id} onChange={(e) => set('admin_id', e.target.value)} error={errors.admin_id}>
          <option value="">{t('Biriktirilmagan')}</option>
          {admins.data?.map((admin) => (
            <option key={admin.id} value={admin.id}>
              {admin.name}
            </option>
          ))}
        </Select>
        <label className="flex items-center gap-3 self-end pb-2.5 sm:col-span-2">
          <Switch checked={form.is_active} onChange={(value) => set('is_active', value)} label={t('Faol')} />
          <span className="text-sm font-medium">{t('Faol')}</span>
        </label>
      </form>
    </Modal>
  )
}

export default function GroupsPage() {
  const t = useT()
  const navigate = useNavigate()
  const { isSuperAdmin } = useAuth()
  const queryClient = useQueryClient()
  const [course, setCourse] = useState('all')
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<Group | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Group | null>(null)

  const groups = useQuery({
    queryKey: ['groups', 'all'],
    queryFn: async () => (await api.get<{ data: Group[] }>('/api/groups')).data.data,
  })

  const toggle = useMutation({
    mutationFn: async (group: Group) => api.post(`/api/groups/${group.id}/toggle`),
    onMutate: async (group) => {
      queryClient.setQueryData<Group[]>(['groups', 'all'], (current) => current?.map((item) => (item.id === group.id ? { ...item, is_active: !item.is_active } : item)))
    },
    onError: (error) => {
      toast.error(errorMessage(error))
      queryClient.invalidateQueries({ queryKey: ['groups'] })
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['lesson-options'] }),
  })

  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/groups/${id}`),
    onSuccess: () => {
      toast.success(t('Guruh oʻchirildi'))
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const courses = useMemo(() => [...new Set(groups.data?.map((group) => group.course))].sort((a, b) => a - b), [groups.data])
  const filtered = (groups.data ?? []).filter(
    (group) =>
      (course === 'all' || String(group.course) === course) &&
      (!search || group.name.toLocaleLowerCase('uz').includes(search.toLocaleLowerCase('uz')) || group.zoom_user_email.includes(search.toLowerCase())),
  )

  return (
    <>
      <PageHeader
        title={t('Guruhlar')}
        description={isSuperAdmin ? t('Guruhlarni Zoom akkauntlariga biriktiring va masʼul administratorni belgilang.') : t('Sizga biriktirilgan guruhlar. Nofaol guruhga dars yaratib boʻlmaydi.')}
        actions={
          isSuperAdmin && (
            <Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
              {t('Guruh qoʻshish')}
            </Button>
          )
        }
      />

      <div className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <Segmented
          label={t('Kurs')}
          value={course}
          onChange={setCourse}
          options={[{ value: 'all', label: t('Barcha kurslar') }, ...courses.map((value) => ({ value: String(value), label: t('{n}-kurs', { n: value }) }))]}
        />
        <Input aria-label={t('Qidirish')} placeholder={t('Guruh nomi yoki Zoom email')} leading={<Search className="size-4" />} wrapperClassName="lg:w-80" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      <Panel padded={false}>
        {groups.isPending ? (
          <SectionLoader />
        ) : !filtered.length ? (
          <EmptyState
            icon={<UsersRound className="size-5" />}
            title={groups.data?.length ? t('Guruh topilmadi') : t('Hali guruhlar yoʻq')}
            description={groups.data?.length ? t('Qidiruv yoki kurs filtrini oʻzgartiring.') : isSuperAdmin ? t('Avval Zoom akkaunt qoʻshing, soʻng guruh yarating.') : t('Sizga hali guruh biriktirilmagan.')}
            action={isSuperAdmin && !groups.data?.length ? <Button onClick={() => setEditing('new')}>{t('Guruh qoʻshish')}</Button> : undefined}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[48rem] text-left text-sm">
              <thead className="border-b border-steel-100 text-steel-600">
                <tr>
                  <th className="px-5 py-3 font-medium">{t('Guruh')}</th>
                  <th className="px-3 py-3 font-medium">{t('Kurs')}</th>
                  <th className="px-3 py-3 font-medium">{t('Zoom')}</th>
                  <th className="px-3 py-3 font-medium">{t('Masʼul')}</th>
                  <th className="px-3 py-3 text-right font-medium">{t('Darslar')}</th>
                  <th className="px-3 py-3 font-medium">{t('Faol')}</th>
                  {isSuperAdmin && (
                    <th className="w-14 px-3 py-3">
                      <span className="sr-only">{t('Amallar')}</span>
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-steel-100">
                {filtered.map((group) => (
                  <tr key={group.id} className="hover:bg-steel-50/60">
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-2">
                        <Link to={`/groups/${group.id}/students`} className="font-medium text-ink-900 underline-offset-4 hover:text-azure-700 hover:underline">
                          {group.name}
                        </Link>
                        {group.live && (
                          <Badge tone="onair" className="gap-2">
                            <OnAirDot className="size-2" />
                            {t('Efirda')}
                          </Badge>
                        )}
                      </div>
                      {group.hemis_id && <p className="text-steel-600">HEMIS {group.hemis_id}</p>}
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap">{group.course}-kurs</td>
                    <td className="px-3 py-3">
                      <p className="text-ink-800">{group.zoom_user_email}</p>
                      <p className="text-steel-600">{group.zoom_account?.name ?? t('Akkaunt yoʻq')}</p>
                    </td>
                    <td className="px-3 py-3 text-steel-700">{group.admin?.name ?? <span className="text-steel-400">—</span>}</td>
                    <td className="px-3 py-3 text-right tabular-nums">{group.lessons_count}</td>
                    <td className="px-3 py-3">
                      <Switch checked={group.is_active} onChange={() => toggle.mutate(group)} label={t('{name} faolligi', { name: group.name })} />
                    </td>
                    {isSuperAdmin && (
                      <td className="px-3 py-2 text-right">
                        <ActionMenu
                          items={[
                            { label: t('Talabalar'), icon: <UsersRound className="size-4" />, onClick: () => navigate(`/groups/${group.id}/students`) },
                            { label: t('Tahrirlash'), icon: <Pencil className="size-4" />, onClick: () => setEditing(group) },
                            { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(group) },
                          ]}
                        />
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {editing && <GroupModal group={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title={t('Guruhni oʻchirasizmi?')}
        description={t('{name} roʻyxatdan olib tashlanadi. Avval oʻtkazilgan darslar hisobotlarda qoladi.', { name: deleting?.name ?? '' })}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}
