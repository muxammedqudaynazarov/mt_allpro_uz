import { useEffect, useState, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { Dialog, DialogBackdrop, DialogPanel } from '@headlessui/react'
import {
  BarChart3,
  CalendarClock,
  Clock3,
  LayoutDashboard,
  LogOut,
  Menu as MenuIcon,
  PlusCircle,
  RadioTower,
  ScrollText,
  UserRound,
  Users,
  UsersRound,
  Video,
  X,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '@/auth/AuthProvider'
import { api } from '@/lib/api'
import { ROLE_LABELS, topRole } from '@/lib/labels'
import type { Role } from '@/lib/types'
import { Avatar } from '@/components/ui/Avatar'
import { LanguageSwitch } from '@/components/ui/LanguageSwitch'
import { Logo } from '@/components/ui/Logo'
import { cn } from '@/components/ui/cn'
import { useT } from '@/i18n/I18nProvider'

interface NavItem {
  to: string
  label: string
  icon: ReactNode
  roles?: Role[]
  /** Faqat shu ruxsat bo'lsa ko'rinadi (masalan, O'quv bo'limi dars yaratmaydi). */
  permission?: string
  end?: boolean
  badge?: number | null
}

interface NavSection {
  title?: string
  items: NavItem[]
}

function useNav(): NavSection[] {
  const t = useT()
  const { isSuperAdmin, user } = useAuth()
  const pending = useQuery({
    queryKey: ['users', 'pending-count'],
    queryFn: async () => (await api.get<{ data: { pending: number } }>('/api/users/counts')).data.data.pending,
    enabled: isSuperAdmin && user?.status === 'active',
    refetchInterval: 60_000,
  })

  return [
    {
      items: [
        { to: '/', label: t('Bosh sahifa'), icon: <LayoutDashboard />, end: true },
        { to: '/lessons/new', label: t('Dars yaratish'), icon: <PlusCircle />, permission: 'lessons.create' },
        { to: '/lessons', label: t('Darslar'), icon: <CalendarClock />, end: true },
        { to: '/monitor', label: t('Monitoring'), icon: <RadioTower />, permission: 'lessons.monitor' },
      ],
    },
    {
      title: t('Boshqaruv'),
      items: [
        { to: '/users', label: t('Foydalanuvchilar'), icon: <Users />, roles: ['education', 'super_admin'], badge: isSuperAdmin ? pending.data : null },
        { to: '/groups', label: t('Guruhlar'), icon: <UsersRound />, roles: ['education', 'super_admin'] },
        { to: '/reports', label: t('Hisobotlar'), icon: <BarChart3 />, roles: ['education', 'super_admin'] },
      ],
    },
    {
      title: t('Sozlamalar'),
      items: [
        { to: '/zoom-accounts', label: t('Zoom akkauntlar'), icon: <Video />, roles: ['super_admin'] },
        { to: '/slots', label: t('Dars vaqtlari'), icon: <Clock3 />, roles: ['super_admin'] },
        { to: '/audit', label: t('Amallar tarixi'), icon: <ScrollText />, roles: ['super_admin'] },
      ],
    },
  ]
}

function NavList({ onNavigate }: { onNavigate?: () => void }) {
  const t = useT()
  const { hasRole, can, user } = useAuth()
  const sections = useNav()

  if (user?.status !== 'active') {
    return (
      <div className="flex-1 px-6 text-sm leading-relaxed text-ink-300">
        {t('Hisobingiz tasdiqlangach, menyu shu yerda paydo boʻladi.')}
      </div>
    )
  }

  return (
    <nav className="flex-1 space-y-7 overflow-y-auto px-3 py-2" aria-label={t('Asosiy menyu')}>
      {sections.map((section, index) => {
        const items = section.items.filter((item) => (!item.roles || hasRole(...item.roles)) && (!item.permission || can(item.permission)))
        if (!items.length) return null
        return (
          <div key={section.title ?? index}>
            {section.title && <p className="mb-2 px-3 text-xs font-medium text-ink-300">{section.title}</p>}
            <ul className="space-y-0.5">
              {items.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      cn(
                        'group flex h-10 items-center gap-3 rounded-field px-3 text-[0.9375rem] transition-colors [&_svg]:size-[1.15rem]',
                        isActive ? 'bg-white/10 font-medium text-white' : 'text-ink-200 hover:bg-white/5 hover:text-white',
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        <span className={cn(isActive ? 'text-azure-300' : 'text-ink-300 group-hover:text-ink-100')}>{item.icon}</span>
                        <span className="flex-1">{item.label}</span>
                        {item.badge ? (
                          <span className="rounded-full bg-amber-500 px-2 text-xs font-semibold text-white tabular-nums">{item.badge}</span>
                        ) : null}
                      </>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        )
      })}
    </nav>
  )
}

function UserCard({ onNavigate }: { onNavigate?: () => void }) {
  const t = useT()
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  if (!user) return null

  return (
    <div className="border-t border-white/10 p-3">
      <div className="flex items-center justify-between px-2 pb-3">
        <span className="text-xs text-ink-300">{t('Til')}</span>
        <LanguageSwitch tone="dark" />
      </div>
      <NavLink
        to="/profile"
        onClick={onNavigate}
        className={({ isActive }) =>
          cn('flex items-center gap-3 rounded-field p-2 transition-colors hover:bg-white/5', isActive && 'bg-white/10')
        }
      >
        <Avatar name={user.name} src={user.picture_url} className="ring-white/20" />
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium text-white">{user.short_name}</span>
          <span className="block truncate text-xs text-ink-300">{t(ROLE_LABELS[topRole(user.roles)])}</span>
        </span>
        <UserRound className="size-4 text-ink-300" aria-hidden />
      </NavLink>
      <button
        type="button"
        onClick={async () => {
          await logout()
          navigate('/login', { replace: true })
        }}
        className="mt-1 flex h-9 w-full items-center gap-3 rounded-field px-3 text-sm text-ink-300 transition-colors hover:bg-white/5 hover:text-white"
      >
        <LogOut className="size-4" />
        {t('Chiqish')}
      </button>
    </div>
  )
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const t = useT()
  return (
    <div className="flex h-full flex-col bg-ink-900">
      <div className="flex items-center gap-3 px-5 pt-6 pb-7">
        <span className="rounded-[0.6rem] bg-white p-1.5">
          <Logo className="size-7" />
        </span>
        <span className="leading-tight">
          <span className="block font-display text-[0.95rem] font-semibold text-white">{t('Masofaviy taʼlim')}</span>
          <span className="block text-xs text-ink-300">{t('Qoraqalpoq davlat universiteti')}</span>
        </span>
      </div>
      <NavList onNavigate={onNavigate} />
      <UserCard onNavigate={onNavigate} />
    </div>
  )
}

export function AppShell() {
  const t = useT()
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  useEffect(() => {
    setMobileOpen(false)
  }, [location.pathname])

  return (
    <div className="min-h-dvh">
      <a href="#content" className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-field focus:bg-white focus:px-4 focus:py-2">
        {t('Asosiy qismga oʻtish')}
      </a>

      <aside className="fixed inset-y-0 left-0 z-30 hidden w-68 lg:block">
        <SidebarContent />
      </aside>

      <Dialog open={mobileOpen} onClose={setMobileOpen} className="relative z-50 lg:hidden">
        <DialogBackdrop transition className="fixed inset-0 bg-ink-950/50 transition-opacity data-closed:opacity-0" />
        <DialogPanel transition className="fixed inset-y-0 left-0 w-72 max-w-[85vw] transition-transform data-closed:-translate-x-full">
          <SidebarContent onNavigate={() => setMobileOpen(false)} />
          <button
            type="button"
            onClick={() => setMobileOpen(false)}
            className="absolute top-5 -right-12 rounded-full bg-white/10 p-2 text-white"
            aria-label={t('Menyuni yopish')}
          >
            <X className="size-5" />
          </button>
        </DialogPanel>
      </Dialog>

      <div className="lg:pl-68">
        <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-steel-200/70 bg-white/90 px-4 backdrop-blur lg:hidden">
          <button type="button" onClick={() => setMobileOpen(true)} className="-ml-1 rounded-field p-2 text-ink-800 hover:bg-steel-100" aria-label={t('Menyuni ochish')}>
            <MenuIcon className="size-5" />
          </button>
          <Logo className="size-7" />
          <span className="flex-1 font-display text-sm font-semibold">{t('Masofaviy taʼlim')}</span>
          <LanguageSwitch />
        </header>

        <main id="content" className="w-full px-4 py-7 sm:px-6 lg:px-10 lg:py-10 2xl:px-14">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

