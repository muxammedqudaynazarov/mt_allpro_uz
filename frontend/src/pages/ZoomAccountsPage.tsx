import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, ExternalLink, Pencil, Plug, Plus, Trash2, TriangleAlert, Video } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { ZoomAccount } from '@/lib/types'
import { ActionMenu } from '@/components/ui/ActionMenu'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { EmptyState } from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { PageHeader } from '@/components/ui/Page'
import { SectionLoader } from '@/components/ui/Spinner'
import { Switch } from '@/components/ui/Switch'

function AccountModal({ account, onClose }: { account: ZoomAccount | 'new'; onClose: () => void }) {
  const queryClient = useQueryClient()
  const editing = account !== 'new'
  const [form, setForm] = useState({
    name: editing ? account.name : '',
    account_id: editing ? account.account_id : '',
    client_id: editing ? account.client_id : '',
    client_secret: '',
    is_active: editing ? account.is_active : true,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  const save = useMutation({
    mutationFn: async () => {
      const payload = { ...form, client_secret: form.client_secret || undefined }
      return editing ? api.put(`/api/zoom-accounts/${account.id}`, payload) : api.post('/api/zoom-accounts', payload)
    },
    onSuccess: () => {
      toast.success('Zoom akkaunt saqlandi')
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
      title={editing ? 'Zoom akkauntni tahrirlash' : 'Zoom akkaunt qoʻshish'}
      description="Zoom Marketplace’da yaratilgan Server-to-Server OAuth ilovasining maʼlumotlari."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Bekor qilish
          </Button>
          <Button type="submit" form="zoom-form" loading={save.isPending}>
            Saqlash
          </Button>
        </>
      }
    >
      <form id="zoom-form" onSubmit={submit} className="space-y-4">
        <Input label="Nomi" placeholder="Masalan: Asosiy akkaunt" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name} required />
        <Input label="Account ID" value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })} error={errors.account_id} required autoComplete="off" />
        <Input label="Client ID" value={form.client_id} onChange={(e) => setForm({ ...form, client_id: e.target.value })} error={errors.client_id} required autoComplete="off" />
        <Input
          label="Client Secret"
          type="password"
          autoComplete="new-password"
          value={form.client_secret}
          onChange={(e) => setForm({ ...form, client_secret: e.target.value })}
          error={errors.client_secret}
          hint={editing ? 'Oʻzgartirmoqchi boʻlmasangiz, boʻsh qoldiring. Saqlangan kalit hech qachon koʻrsatilmaydi.' : 'Shifrlangan holda saqlanadi va qayta koʻrsatilmaydi.'}
          required={!editing}
        />
        <label className="flex items-center gap-3">
          <Switch checked={form.is_active} onChange={(value) => setForm({ ...form, is_active: value })} label="Faol" />
          <span className="text-sm font-medium">Faol</span>
        </label>
      </form>
    </Modal>
  )
}

export default function ZoomAccountsPage() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<ZoomAccount | 'new' | null>(null)
  const [deleting, setDeleting] = useState<ZoomAccount | null>(null)

  const accounts = useQuery({
    queryKey: ['zoom-accounts'],
    queryFn: async () => (await api.get<{ data: ZoomAccount[] }>('/api/zoom-accounts')).data.data,
  })

  const test = useMutation({
    mutationFn: async (account: ZoomAccount) => (await api.post<{ data: { users: number } }>(`/api/zoom-accounts/${account.id}/test`)).data.data,
    onSuccess: (data) => toast.success(`Ulanish ishlayapti: ${data.users} ta Zoom foydalanuvchisi topildi`),
    onError: (error) => toast.error(errorMessage(error, 'Zoom’ga ulanib boʻlmadi.')),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] }),
  })

  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(`/api/zoom-accounts/${id}`),
    onSuccess: () => {
      toast.success('Zoom akkaunt oʻchirildi')
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['zoom-accounts'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <PageHeader
        title="Zoom akkauntlar"
        description="Darslar shu akkauntlar orqali yaratiladi. Kirish kalitlari serverda shifrlangan holda saqlanadi."
        actions={
          <Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>
            Akkaunt qoʻshish
          </Button>
        }
      />

      {accounts.isPending ? (
        <SectionLoader />
      ) : !accounts.data?.length ? (
        <EmptyState
          className="rounded-panel bg-white ring-1 ring-steel-200"
          icon={<Video className="size-5" />}
          title="Zoom akkauntlar qoʻshilmagan"
          description="Zoom Marketplace’da Server-to-Server OAuth ilova yarating va uning Account ID, Client ID, Client Secret qiymatlarini shu yerga kiriting."
          action={<Button onClick={() => setEditing('new')}>Akkaunt qoʻshish</Button>}
        />
      ) : (
        <ul className="grid gap-4 md:grid-cols-2">
          {accounts.data.map((account) => (
            <li key={account.id} className="rounded-panel bg-white p-5 ring-1 ring-steel-200/70">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="truncate text-base font-semibold">{account.name}</p>
                    {!account.is_active && <Badge>Nofaol</Badge>}
                  </div>
                  <p className="mt-0.5 text-sm text-steel-600">{account.groups_count} ta guruh biriktirilgan</p>
                </div>
                <ActionMenu
                  items={[
                    { label: 'Tahrirlash', icon: <Pencil className="size-4" />, onClick: () => setEditing(account) },
                    { label: 'Oʻchirish', icon: <Trash2 className="size-4" />, danger: true, onClick: () => setDeleting(account) },
                  ]}
                />
              </div>
              <dl className="mt-4 grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt className="text-steel-600">Account ID</dt>
                <dd className="truncate text-ink-800">{account.account_id}</dd>
                <dt className="text-steel-600">Client ID</dt>
                <dd className="truncate text-ink-800">{account.client_id}</dd>
                <dt className="text-steel-600">Secret</dt>
                <dd className="text-ink-800">{account.has_secret ? 'Saqlangan' : <span className="text-onair-600">Kiritilmagan</span>}</dd>
              </dl>
              <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-steel-100 pt-4">
                <p className="flex items-center gap-2 text-sm">
                  {account.last_error ? (
                    <>
                      <TriangleAlert className="size-4 text-onair-500" />
                      <span className="text-onair-600" title={account.last_error}>
                        Oxirgi ulanishda xato
                      </span>
                    </>
                  ) : account.last_checked_at ? (
                    <>
                      <CheckCircle2 className="size-4 text-leaf-500" />
                      <span className="text-steel-600">Tekshirildi: {formatDateTime(account.last_checked_at)}</span>
                    </>
                  ) : (
                    <span className="text-steel-500">Hali tekshirilmagan</span>
                  )}
                </p>
                <Button
                  size="sm"
                  variant="secondary"
                  icon={<Plug className="size-4" />}
                  loading={test.isPending && test.variables?.id === account.id}
                  onClick={() => test.mutate(account)}
                >
                  Ulanishni tekshirish
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <p className="mt-6 flex items-center gap-2 text-sm text-steel-600">
        <ExternalLink className="size-4" />
        <a href="https://marketplace.zoom.us/develop/create" target="_blank" rel="noopener noreferrer" className="underline-offset-4 hover:text-ink-900 hover:underline">
          Zoom Marketplace’da Server-to-Server OAuth ilova yaratish
        </a>
      </p>

      {editing && <AccountModal account={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={!!deleting}
        title="Zoom akkauntni oʻchirasizmi?"
        description={deleting?.groups_count ? `Bu akkauntga ${deleting.groups_count} ta guruh biriktirilgan. Avval guruhlarni boshqa akkauntga oʻtkazing.` : 'Akkaunt maʼlumotlari butunlay oʻchiriladi.'}
        confirmLabel="Oʻchirish"
        tone="danger"
        loading={remove.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
      />
    </>
  )
}
