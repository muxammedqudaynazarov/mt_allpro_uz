import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from './AuthProvider'
import { FullPageLoader } from '@/components/ui/Spinner'
import type { Role } from '@/lib/types'

/** Faqat tizimga kirgan foydalanuvchilar uchun. */
export function RequireAuth() {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <FullPageLoader />
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />

  // Tasdiqlanmagan xodim faqat kutish sahifasi va profilni ko'ra oladi.
  const allowedWhilePending = ['/pending', '/profile']
  if (user.status !== 'active' && !allowedWhilePending.includes(location.pathname)) {
    return <Navigate to="/pending" replace />
  }

  return <Outlet />
}

/** Tizimga kirgan foydalanuvchini login sahifasidan uzoqlashtiradi. */
export function GuestOnly({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <FullPageLoader />
  if (user) return <Navigate to={user.status === 'active' ? '/' : '/pending'} replace />
  return <>{children}</>
}

/** Berilgan rollardan biri bo'lmasa, bosh sahifaga qaytaradi. */
export function RequireRole({ roles }: { roles: Role[] }) {
  const { hasRole } = useAuth()
  if (!hasRole(...roles)) return <Navigate to="/" replace />
  return <Outlet />
}
