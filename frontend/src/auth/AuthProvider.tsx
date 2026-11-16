import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
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
