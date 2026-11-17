import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { useMutation } from '@tanstack/react-query'
import { QRCodeSVG } from 'qrcode.react'
import { ArrowLeft, Send, Unlink } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/auth/AuthProvider'
import { api, errorMessage } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { ROLE_LABELS, USER_STATUS_LABELS } from '@/lib/labels'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Button, buttonClass } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { PageHeader, Panel } from '@/components/ui/Page'
import { useT } from '@/i18n/I18nProvider'

function TelegramLinkModal({ url, onClose }: { url: string; onClose: () => void }) {
  const t = useT()
  const { user, refresh } = useAuth()

  // Bot orqali ulanganini bilish uchun profilni har 4 soniyada yangilaymiz.
  useEffect(() => {
    const timer = window.setInterval(() => refresh(), 4000)
    return () => window.clearInterval(timer)
  }, [refresh])

  useEffect(() => {
    if (user?.telegram.linked) {
      toast.success(t('Telegram ulandi'))
      onClose()
    }
  }, [user?.telegram.linked, onClose])

  return (
    <Modal open onClose={onClose} title={t('Telegram’ni ulash')} description={t('Havola 15 daqiqa amal qiladi va faqat bir marta ishlaydi.')}>
      <div className="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
        <div className="rounded-field bg-white p-3 ring-1 ring-steel-200">
          <QRCodeSVG value={url} size={148} fgColor="#13284a" />
        </div>
        <ol className="space-y-3 text-sm leading-relaxed text-steel-700">
          <li>
            <span className="font-medium text-ink-900">{t('Telefonda:')}</span> {t('QR kodni kamera bilan skanerlang.')}
          </li>
          <li>
            <span className="font-medium text-ink-900">{t('Kompyuterda:')}</span> {t('pastdagi tugmani bosing.')}
          </li>
          <li>{t('Botda “Start” tugmasini bosing — hisob avtomatik ulanadi.')}</li>
        </ol>
      </div>
      <a href={url} target="_blank" rel="noopener noreferrer" className={buttonClass({ className: 'mt-6 w-full' })}>
        <Send className="size-4" />
        {t('Telegram’da ochish')}
      </a>
    </Modal>
  )
}

export default function ProfilePage() {
  const t = useT()
  const { user, config, refresh } = useAuth()
  const [linkUrl, setLinkUrl] = useState<string | null>(null)

  const link = useMutation({
    mutationFn: async () => (await api.post<{ data: { url: string } }>('/api/me/telegram')).data.data.url,
    onSuccess: setLinkUrl,
    onError: (error) => toast.error(errorMessage(error)),
  })

  const unlink = useMutation({
    mutationFn: async () => api.delete('/api/me/telegram'),
    onSuccess: async () => {
      await refresh()
      toast.success(t('Telegram uzildi'))
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  if (!user) return null

  const rows = [
    { label: t('HEMIS ID'), value: user.hemis_id },
    { label: t('Boʻlim'), value: user.department },
    { label: t('Telefon'), value: user.phone },
    { label: t('Email'), value: user.email },
    { label: t('Masʼul administrator'), value: user.managed_by?.name },
    { label: t('Oxirgi kirish'), value: user.last_login_at ? formatDateTime(user.last_login_at) : null },
  ]

  return (
    <>
      {user.status !== 'active' && (
        <Link to="/pending" className="mb-6 inline-flex items-center gap-2 text-sm text-steel-600 hover:text-ink-900">
          <ArrowLeft className="size-4" />
          {t('Ortga')}
        </Link>
      )}
      <PageHeader title={t('Profil')} description={t('Maʼlumotlar HEMIS’dan olinadi va har kirishda yangilanadi.')} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <Panel>
          <div className="flex items-center gap-4">
            <Avatar name={user.name} src={user.picture_url} className="size-16 text-base" />
            <div className="min-w-0">
              <p className="text-lg font-semibold">{user.name}</p>
              <div className="mt-1.5 flex flex-wrap gap-1.5">
                {user.roles.map((role) => (
                  <Badge key={role} tone="azure">
                    {t(ROLE_LABELS[role])}
                  </Badge>
                ))}
                <Badge tone={user.status === 'active' ? 'leaf' : 'amber'} dot>
                  {t(USER_STATUS_LABELS[user.status])}
                </Badge>
              </div>
            </div>
          </div>
          <dl className="mt-6 divide-y divide-steel-100 border-t border-steel-100">
            {rows.map((row) => (
              <div key={row.label} className="grid grid-cols-[11rem_1fr] gap-3 py-3 text-sm">
                <dt className="text-steel-600">{row.label}</dt>
                <dd className="text-ink-900">{row.value || <span className="text-steel-400">—</span>}</dd>
              </div>
            ))}
          </dl>
        </Panel>

        <Panel title={t('Telegram')} className="self-start">
          {!config?.telegram_enabled ? (
            <p className="text-sm text-steel-600">{t('Telegram bot hali sozlanmagan.')}</p>
          ) : user.telegram.linked ? (
            <>
              <p className="text-sm leading-relaxed text-steel-700">
                {user.telegram.username
                  ? t('Hisobingiz @{username} ga ulangan. Yangi dars havolalari va tizim xabarlari shu yerga keladi.', { username: user.telegram.username })
                  : t('Hisobingiz Telegram’ga ulangan. Yangi dars havolalari va tizim xabarlari shu yerga keladi.')}
              </p>
              <Button variant="danger" size="sm" className="mt-4" icon={<Unlink className="size-4" />} loading={unlink.isPending} onClick={() => unlink.mutate()}>
                {t('Uzish')}
              </Button>
            </>
          ) : (
            <>
              <p className="text-sm leading-relaxed text-steel-700">
                {t('Ulangandan soʻng har bir yangi dars uchun “Darsni boshlash” va “Guruhga yuborish” tugmalari {bot} orqali keladi.', {
                  bot: config.telegram_bot_username ? `@${config.telegram_bot_username}` : 'bot',
                })}
              </p>
              <Button className="mt-4 w-full" icon={<Send className="size-4" />} loading={link.isPending} onClick={() => link.mutate()}>
                {t('Telegram’ni ulash')}
              </Button>
            </>
          )}
        </Panel>
      </div>

      {linkUrl && <TelegramLinkModal url={linkUrl} onClose={() => setLinkUrl(null)} />}
    </>
  )
}
