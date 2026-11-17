import { createContext, useCallback, useContext, useEffect, useMemo, useRef, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useI18n } from '@/i18n/I18nProvider'
import { DEFAULT_LOCALE, guestLocale, isLocale, rememberUserLocale, takeGuestLocale } from '@/i18n/locale'
import { api, isStatus } from '@/lib/api'
import type { AppConfig, Me, Role } from '@/lib/types'

interface AuthContextValue {
  user: Me | null
  config: AppConfig | undefined
  loading: boolean
  hasRole: (...roles: Role[]) => boolean
  can: (permission: string) => boolean
  isAdmin: boolean
  isSuperAdmin: boolean
  refresh: () => Promise<unknown>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export const ME_KEY = ['me'] as const

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const configQuery = useQuery({
    queryKey: ['config'],
    queryFn: async () => (await api.get<{ data: AppConfig }>('/api/config')).data.data,
    staleTime: Infinity,
  })

  const meQuery = useQuery({
    queryKey: ME_KEY,
    queryFn: async () => {
      try {
        return (await api.get<{ data: Me }>('/api/me')).data.data
      } catch (error) {
        if (isStatus(error, 401)) return null
        throw error
      }
    },
    staleTime: 60_000,
    retry: (count, error) => !isStatus(error, 401) && count < 2,
  })

  const user = meQuery.data ?? null
  const { locale, setLocale } = useI18n()
  const localeRef = useRef(locale)
  localeRef.current = locale

  // Til: mehmonga — asosiy til (yoki shu oynada tanlagani), kirgan foydalanuvchiga — profilidagi til.
  const authKey = meQuery.isPending ? null : user ? `${user.id}:${user.locale ?? ''}` : 'guest'
  useEffect(() => {
    if (authKey === null) return
    let target = guestLocale() ?? DEFAULT_LOCALE

    if (user) {
      const chosen = takeGuestLocale()
      if (chosen && chosen !== user.locale) {
        // Kirishdan oldin tanlangan til profilga yoziladi (Telegram xabarlari ham shu tilda bo'ladi).
        queryClient.setQueryData(ME_KEY, (current: Me | null | undefined) => (current ? { ...current, locale: chosen } : current))
        api.patch('/api/me', { locale: chosen }).catch(() => undefined)
      }
      target = chosen ?? (isLocale(user.locale) ? user.locale : DEFAULT_LOCALE)
      rememberUserLocale(target)
    } else {
      rememberUserLocale(null)
    }

    if (target !== localeRef.current) setLocale(target)
    // Faqat kirish holati yoki profil tili o'zgarganda ishlaydi.
  }, [authKey])

  const hasRole = useCallback((...roles: Role[]) => !!user && roles.some((role) => user.roles.includes(role)), [user])
  const can = useCallback((permission: string) => !!user && user.permissions.includes(permission), [user])

  const logout = useCallback(async () => {
    try {
      await api.post('/api/auth/logout')
    } finally {
      queryClient.setQueryData(ME_KEY, null)
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== 'config' && query.queryKey[0] !== 'me' })
    }
  }, [queryClient])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      config: configQuery.data,
      loading: meQuery.isPending,
      hasRole,
      can,
      isAdmin: hasRole('admin', 'super_admin'),
      isSuperAdmin: hasRole('super_admin'),
      refresh: () => queryClient.invalidateQueries({ queryKey: ME_KEY }),
      logout,
    }),
    [user, configQuery.data, meQuery.isPending, hasRole, can, queryClient, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth AuthProvider ichida ishlatilishi kerak')
  return context
}
