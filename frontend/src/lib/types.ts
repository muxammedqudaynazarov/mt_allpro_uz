export type Role = 'super_admin' | 'admin' | 'teacher'
export type UserStatus = 'pending' | 'active' | 'blocked'
export type LessonStatus = 'scheduled' | 'live' | 'finished' | 'cancelled'

export interface UserRef {
  id: number
  name: string
}

export interface Me {
  id: number
  name: string
  first_name: string
  short_name: string
  email: string | null
  phone: string | null
  picture_url: string | null
  department: string | null
  hemis_id: string | null
  status: UserStatus
  locale: 'kaa' | 'uz'
  roles: Role[]
  permissions: string[]
  has_password: boolean
  telegram: { linked: boolean; username: string | null }
  managed_by: UserRef | null
  last_login_at: string | null
  created_at: string
}

export interface AppConfig {
  app_name: string
  university: string
  hemis_enabled: boolean
  telegram_enabled: boolean
  telegram_bot_username: string | null
  default_duration: number
}

export interface GroupRef {
  id: number
  name: string
  course: number
}

export interface SlotRef {
  id: number
  name: string
  starts_at: string
}

export interface Lesson {
  id: number
  topic: string
  status: LessonStatus
  starts_at: string
  ends_at: string
  ended_at: string | null
  join_url: string | null
  passcode: string | null
  group: GroupRef
  teacher: UserRef
  slot: SlotRef | null
  can: { start: boolean; end: boolean }
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface LessonSlot {
  id: number
  name: string
  starts_at: string
  ends_at: string
  duration_minutes: number
  is_active: boolean
  sort: number
}

export interface LessonOptionGroup extends GroupRef {
  busy: boolean
  busy_until: string | null
}

export interface LessonOptionSlot {
  id: number
  name: string
  starts_at: string
  ends_at: string
  duration_minutes: number
  available: boolean
}

export interface LessonOptions {
  now: string
  default_duration: number
  groups: LessonOptionGroup[]
  slots: LessonOptionSlot[]
}

export interface Group {
  id: number
  name: string
  hemis_id: string | null
  course: number
  is_active: boolean
  zoom_user_email: string
  zoom_account: { id: number; name: string } | null
  admin: UserRef | null
  lessons_count: number
  live: boolean
}

export interface ZoomAccount {
  id: number
  name: string
  account_id: string
  client_id: string
  has_secret: boolean
  is_active: boolean
  groups_count: number
  last_checked_at: string | null
  last_error: string | null
}

export interface ZoomUser {
  id: string
  email: string
  name: string
  type: number
}

export interface UserListItem {
  id: number
  name: string
  picture_url: string | null
  hemis_id: string | null
  phone: string | null
  department: string | null
  status: UserStatus
  roles: Role[]
  has_password: boolean
  telegram_linked: boolean
  managed_by: UserRef | null
  lessons_count: number
  last_login_at: string | null
  created_at: string
}

export interface DashboardData {
  stats: {
    live_now: number
    today_total: number
    month_total: number
    pending_users: number | null
  }
  live: Lesson[]
  today: Lesson[]
  slots: LessonSlot[]
}

export interface AuditLog {
  id: number
  action: string
  description: string
  user: UserRef | null
  subject_type: string | null
  subject_id: number | null
  properties: Record<string, unknown> | null
  ip: string | null
  created_at: string
}

export interface ReportSummary {
  from: string
  to: string
  totals: { lessons: number; minutes: number; teachers: number; groups: number }
  by_teacher: { teacher: UserRef; lessons: number; minutes: number }[]
  by_group: { group: GroupRef; lessons: number; minutes: number }[]
}
