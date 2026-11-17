import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Copy, ExternalLink, Pencil, Plug, Plus, RefreshCw, Trash2, TriangleAlert, Video, Webhook } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { ZoomAccount, ZoomHost } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'
import { copyText } from '@/features/lessons/useLessonActions'
import { useT } from '@/i18n/I18nProvider'

function AccountModal({ account, onClose }: { account: ZoomAccount | 'new'; onClose: () => void }) {
  const t = useT()
  const queryClient = useQueryClient()
  const editing = account !== 'new'
  const [form, setForm] = useState({
    name: editing ? account.name : '',
    account_id: editing ? account.account_id : '',
    client_id: editing ? account.client_id : '',
    client_secret: '',
    webhook_secret: '',
    remove_webhook_secret: false,
    is_active: editing ? account.is_active : true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const save = useMutation({
    mutationFn: async () => {
      const payload = { ...form, client_secret: form.client_secret || undefined, webhook_secret: form.webhook_secret || undefined }
      return editing ? api.put(`/api/zoom-accounts/${account.id}`, payload) : api.post('/api/zoom-accounts', payload)
    },
    onSuccess: () => {
      toast.success(t('Zoom akkaunt saqlandi'))
      queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] })
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
      title={editing ? t('Zoom akkauntni tahrirlash') : t('Zoom akkaunt qoʻshish')}
      description={t('Zoom Marketplace’da yaratilgan Server-to-Server OAuth ilovasining maʼlumotlari.')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            {t('Bekor qilish')}
          </Button>
          <Button type="submit" form="zoom-form" loading={save.isPending}>
            {t('Saqlash')}
          </Button>
        </>
      }
    >
      <form id="zoom-form" onSubmit={submit} className="space-y-4">
        <Input label={t('Nomi')} placeholder={t('Masalan: Asosiy akkaunt')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} required />
        <Input label={t('Account ID')} value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })} error={errors.account_id} required autoComplete="off" />
        <Input label={t('Client ID')} value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} error={errors.client_id} required autoComplete="off" />
        <Input
          label={t('Client Secret')}
          type="password"
          autoComplete="new-password"
          value={form.client_secret}
          onChange={(e) => setForm({ ...form, client_secret: e.target.value })}
          error={errors.client_secret}
          hint={editing ? t('Oʻzgartirmoqchi boʻlmasangiz, boʻsh qoldiring. Saqlangan kalit hech qachon koʻrsatilmaydi.') : t('Shifrlangan holda saqlanadi va qayta koʻrsatilmaydi.')}
          required={!editing}
        />
        <Input
          label={t('Webhook Secret Token')}
          type="password"
          autoComplete="new-password"
          value={form.webhook_secret}
          onChange={(e) => setForm({ ...form, webhook_secret: e.target.value, remove_webhook_secret: false })}
          error={errors.webhook_secret}
          hint={
            editing && account.has_webhook_secret
              ? t('Saqlangan. Oʻzgartirmoqchi boʻlmasangiz, boʻsh qoldiring.')
              : t('Ixtiyoriy. Zoom ilovasi → Feature → Event Subscriptions sahifasidagi Secret Token. Jonli qatnashchilar roʻyxati uchun kerak.')
          }
        />
        {editing && account.has_webhook_secret && (
          <label className="flex items-center gap-3 text-sm">
            <input
              type="checkbox"
              className="size-4 rounded border-steel-300"
              checked={form.remove_webhook_secret}
              onChange={(e) => setForm({ ...form, remove_webhook_secret: e.target.checked, webhook_secret: '' })}
            />
            {t('Webhook’ni oʻchirish (Secret Token’ni olib tashlash)')}
          </label>
        )}
        <label className="flex items-center gap-3">
          <Switch checked={form.is_active} onChange={(value) => setForm({ ...form, is_active: value })} label={t('Faol')} />
          <span className="text-sm font-medium">{t('Faol')}</span>
        </label>
      </form>
    </Modal>
  )
}

export default function ZoomAccountsPage() {
  const t = useT()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<ZoomAccount | 'new' | null>(null)
  const [deleting, setDeleting] = useState<ZoomAccount | null>(null)

  const accounts = useQuery({
    queryKey: ['zoom-accounts'],
    queryFn: async () => (await api.get<{ data: ZoomAccount[] }>('/api/zoom-accounts')).data.data,
  })

  const test = useMutation({
    mutationFn: async (account: ZoomAccount) => (await api.post<{ data: { users: number } }>(`/api/zoom-accounts/${account.id}/test`)).data.data,
    onSuccess: (data) => toast.success(t('Ulanish ishlayapti: {count} ta Zoom foydalanuvchisi topildi', { count: data.users })),
    onError: (error) => toast.error(errorMessage(error, t('Zoom’ga ulanib boʻlmadi.'))),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] }),
  })

  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/zoom-accounts/${id}`),
    onSuccess: () => {
      toast.success(t('Zoom akkaunt oʻchirildi'))
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <PageHeader
        title={t('Zoom akkauntlar')}
        description={t('Darslar shu akkauntlar orqali yaratiladi. Kirish kalitlari serverda shifrlangan holda saqlanadi.')}
        actions={
          <Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
            {t('Akkaunt qoʻshish')}
          </Button>
        }
      />

      {accounts.isPending ? (
        <SectionLoader />
      ) : !accounts.data?.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          icon={<Video className="size-5" />}
          title={t('Zoom akkauntlar qoʻshilmagan')}
          description={t('Zoom Marketplace’da Server-to-Server OAuth ilova yarating va uning Account ID, Client ID, Client Secret qiymatlarini shu yerga kiriting.')}
          action={<Button onClick={() => setEditing('new')}>{t('Akkaunt qoʻshish')}</Button>}
        />
      ) : (
        <ul className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
          {accounts.data.map((account) => (
            <li key={account.id} className="rounded-panel bg-white p-5 ring-1 ring-steel-200/70">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="truncate text-base font-semibold">{account.name}</p>
                    {!account.is_active && <Badge>{t('Nofaol')}</Badge>}
                  </div>
                  <p className="mt-0.5 text-sm text-steel-600">{t('{hosts} ta host, {groups} ta guruh', { hosts: account.hosts_count, groups: account.groups_count })}</p>
                </div>
                <ActionMenu
                  items={[
                    { label: t('Tahrirlash'), icon: <Pencil className="size-4" />, onClick: () => setEditing(account) },
                    { label: t('Oʻchirish'), icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(account) },
                  ]}
                />
              </div>
              <dl className="mt-4 grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt className="text-steel-600">{t('Account ID')}</dt>
                <dd className="truncate text-ink-800">{account.account_id}</dd>
                <dt className="text-steel-600">{t('Client ID')}</dt>
                <dd className="truncate text-ink-800">{account.client_id}</dd>
                <dt className="text-steel-600">{t('Secret')}</dt>
                <dd className="text-ink-800">{account.has_secret ? t('Saqlangan') : <span className="text-onair-600">{t('Kiritilmagan')}</span>}</dd>
                <dt className="text-steel-600">{t('Webhook')}</dt>
                <dd>
                  {!account.has_webhook_secret ? (
                    <span className="text-amber-600">{t('Sozlanmagan')}</span>
                  ) : account.webhook_received_at ? (
                    <span className="text-leaf-600">{t('Ishlayapti, oxirgi hodisa: {date}', { date: formatDateTime(account.webhook_received_at) })}</span>
                  ) : (
                    <span className="text-amber-600">{t('Zoom’dan hali hodisa kelmagan')}</span>
                  )}
                </dd>
              </dl>
              <div className="mt-3 rounded-field bg-mist/70 p-3">
                <p className="flex items-center gap-1.5 text-xs font-medium text-steel-600">
                  <Webhook className="size-3.5" />
                  {t('Event notification endpoint URL')}
                </p>
                <div className="mt-1 flex items-center gap-2">
                  <code className="min-w-0 flex-1 truncate text-xs text-ink-800" title={account.webhook_url}>
                    {account.webhook_url}
                  </code>
                  <Button size="sm" variant="ghost" icon={<Copy className="size-3.5" />} onClick={() => copyText(account.webhook_url)}>
                    {t('Nusxalash')}
                  </Button>
                </div>
              </div>
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-steel-100 pt-4">
                <p className="flex items-center gap-2 text-sm">
                  {account.last_error ? (
                    <>
                      <TriangleAlert className="size-4 text-onair-500" />
                      <span className="text-onair-600" title={account.last_error}>
                        {t('Oxirgi ulanishda xato')}
                      </span>
                    </>
                  ) : account.last_checked_at ? (
                    <>
                      <CheckCircle2 className="size-4 text-leaf-500" />
                      <span className="text-steel-600">{t('Tekshirildi: {date}', { date: formatDateTime(account.last_checked_at) })}</span>
                    </>
                  ) : (
                    <span className="text-steel-500">{t('Hali tekshirilmagan')}</span>
                  )}
                </p>
                <Button
                  size="sm"
                  variant="secondary"
                  icon={<Plug className="size-4" />}
                  loading={test.isPending && test.variables?.id === account.id}
                  onClick={() => test.mutate(account)}
                >
                  {t('Ulanishni tekshirish')}
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {!!accounts.data?.length && <HostsPanel accounts={accounts.data} />}

      <p className="mt-6 flex items-center gap-2 text-sm text-steel-600">
        <ExternalLink className="size-4" />
        <a href="https://marketplace.zoom.us/develop/create" target="_blank" rel="noopener noreferrer" className="underline-offset-4 hover:text-ink-900 hover:underline">
          {t('Zoom Marketplace’da Server-to-Server OAuth ilova yaratish')}
        </a>
      </p>

      {editing && <AccountModal account={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title={t('Zoom akkauntni oʻchirasizmi?')}
        description={deleting?.groups_count ? t('Bu akkauntga {count} ta guruh biriktirilgan. Avval guruhlarni boshqa akkauntga oʻtkazing.', { count: deleting.groups_count }) : t('Akkaunt maʼlumotlari butunlay oʻchiriladi.')}
        confirmLabel={t('Oʻchirish')}
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}

function HostsPanel({ accounts }: { accounts: ZoomAccount[] }) {
  const t = useT()
  const queryClient = useQueryClient()
  const hosts = useQuery({
    queryKey: ['zoom-hosts'],
    queryFn: async () => (await api.get<{ data: ZoomHost[] }>('/api/zoom-hosts')).data.data,
  })

  const sync = useMutation({
    mutationFn: async (account: ZoomAccount) => (await api.post<{ data: { users: number } }>(`/api/zoom-accounts/${account.id}/sync-hosts`)).data.data,
    onSuccess: (data) => toast.success(t('Hostlar yangilandi: Zoom’da {count} ta foydalanuvchi', { count: data.users })),
    onError: (error) => toast.error(errorMessage(error, t('Zoom’ga ulanib boʻlmadi.'))),
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['zoom-hosts'] })
      queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] })
    },
  })

  const toggle = useMutation({
    mutationFn: async (host: ZoomHost) => api.patch(`/api/zoom-hosts/${host.id}`, { is_active: !host.is_active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['zoom-hosts'] }),
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <Panel
      className="mt-8"
      padded={false}
      title={t('Zoom hostlar')}
      actions={
        <div className="flex flex-wrap gap-2">
          {accounts
            .filter((account) => account.is_active)
            .map((account) => (
              <Button
                key={account.id}
                size="sm"
                variant="secondary"
                icon={<RefreshCw className="size-4" />}
                loading={sync.isPending && sync.variables?.id === account.id}
                onClick={() => sync.mutate(account)}
              >
                {accounts.length > 1 ? t('{name}: Zoom’dan yangilash', { name: account.name }) : t('Zoom’dan yangilash')}
              </Button>
            ))}
        </div>
      }
    >
      <p className="border-b border-steel-100 px-5 py-3 text-sm text-steel-600">
        {t('Oʻqituvchi dars yaratishda shu roʻyxatdagi faol va boʻsh hostlardan birini tanlaydi. Basic (litsenziyasiz) foydalanuvchilar avtomatik yoqilmaydi: ularda uchrashuv 40 daqiqa bilan cheklangan.')}
      </p>
      {hosts.isPending ? (
        <SectionLoader />
      ) : !hosts.data?.length ? (
        <EmptyState title={t('Hostlar yoʻq')} description={t('“Zoom’dan yangilash” tugmasini bosing — akkauntdagi foydalanuvchilar roʻyxatga qoʻshiladi.')} />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[40rem] text-left text-sm">
            <thead className="border-b border-steel-100 text-steel-600">
              <tr>
                <th className="px-5 py-3 font-medium">{t('Host')}</th>
                <th className="px-3 py-3 font-medium">{t('Akkaunt')}</th>
                <th className="px-3 py-3 font-medium">{t('Litsenziya')}</th>
                <th className="px-3 py-3 text-right font-medium">{t('Ochiq darslar')}</th>
                <th className="px-5 py-3 font-medium">{t('Faol')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-steel-100">
              {hosts.data.map((host) => (
                <tr key={host.id} className={host.is_active ? '' : 'text-steel-500'}>
                  <td className="px-5 py-3">
                    <p className={host.is_active ? 'font-medium text-ink-900' : ''}>{host.name}</p>
                    {host.name !== host.email && <p className="text-steel-500">{host.email}</p>}
                  </td>
                  <td className="px-3 py-3">{host.account.name}</td>
                  <td className="px-3 py-3">{host.licensed ? <Badge tone="leaf">{t('Licensed')}</Badge> : <Badge tone="amber">{t('Basic, 40 daqiqa')}</Badge>}</td>
                  <td className="px-3 py-3 text-right tabular-nums">{host.open_lessons}</td>
                  <td className="px-5 py-3">
                    <Switch checked={host.is_active} onChange={() => toggle.mutate(host)} label={t('{name} faolligi', { name: host.email })} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  )
}
