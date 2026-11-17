import type { LessonStatus, Role, UserStatus } from './types'

// Qiymatlar o'zbekcha manba matnlar — ko'rsatishda t(...) orqali tarjima qilinadi.

export const ROLE_LABELS: Record<Role, string> = {
  super_admin: 'Bosh administrator',
  admin: 'Administrator',
  teacher: 'Oʻqituvchi',
}

export const USER_STATUS_LABELS: Record<UserStatus, string> = {
  pending: 'Tasdiq kutmoqda',
  active: 'Faol',
  blocked: 'Bloklangan',
}

export const LESSON_STATUS_LABELS: Record<LessonStatus, string> = {
  scheduled: 'Rejalashtirilgan',
  live: 'Efirda',
  finished: 'Yakunlangan',
  cancelled: 'Bekor qilingan',
}

export const COURSES = [1, 2, 3, 4, 5] as const

export function topRole(roles: Role[]): Role {
  if (roles.includes('super_admin')) return 'super_admin'
  if (roles.includes('admin')) return 'admin'
  return 'teacher'
}
