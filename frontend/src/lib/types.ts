export type Role = 'super_admin' | 'education' | 'teacher'
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
  last_name: string | null
  middle_name: string | null
  short_name: string
  email: string | null
  phone: string | null
  picture_url: string | null
  department: string | null
  hemis_id: string | null
  status: UserStatus
  /** Profilda tanlangan til; null — tanlanmagan (asosiy til ishlatiladi). */
  locale: 'kaa' | 'uz' | null
  roles: Role[]
  permissions: string[]
  has_password: boolean
  telegram: { linked: boolean; username: string | null }
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

/** Darsning Zoom'dagi haqiqiy holati (har daqiqada tekshiriladi). */
export type ZoomState = 'waiting' | 'started' | 'ended' | 'missed'

export interface LessonTracking {
  state: ZoomState | null
  started_at: string | null
  ended_at: string | null
  /** Host jadvaldagi vaqtdan necha daqiqa keyin boshlagani (manfiy — oldinroq). */
  start_delay_minutes: number | null
  /** Zoom'da haqiqatda necha daqiqa davom etgani. */
  minutes: number | null
  participants: number | null
  synced: boolean
  error: string | null
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
  tracking: LessonTracking
  can: { start: boolean; end: boolean }
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export interface LessonSlot {
  id: number
  /** Bazadagi nom (tahrirlash uchun). */
  name: string
  /** Joriy tilda ko'rsatiladigan nom. */
  label: string
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
  first_name: string | null
  last_name: string | null
  middle_name: string | null
  /** O'qituvchi kalendar va har haftalik darslarni boshqara oladimi (Administrator belgilaydi). */
  can_manage_calendar: boolean
  picture_url: string | null
  hemis_id: string | null
  phone: string | null
  department: string | null
  status: UserStatus
  roles: Role[]
  has_password: boolean
  telegram_linked: boolean
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

export interface ZoomStats {
  held: number
  missed: number
  late_starts: number
  avg_start_delay: number | null
  actual_minutes: number
  avg_participants: number | null
}

export interface ReportSummary {
  from: string
  to: string
  totals: { lessons: number; minutes: number; teachers: number; groups: number } & ZoomStats
  by_teacher: ({ teacher: UserRef; lessons: number; minutes: number } & ZoomStats)[]
  by_group: ({ group: GroupRef; lessons: number; minutes: number } & ZoomStats)[]
}

export interface AttendanceRow {
  student_id: number
  name: string
  ignored: boolean
  /** Talaba Zoom'da qanday ismlar bilan kirgan. */
  names: string[]
  first_joined_at: string | null
  last_left_at: string | null
  late_minutes: number
  minutes: number
  percent: number | null
  sessions: number
  /** Zoom'da bo'lgan oraliqlar [kirdi, chiqdi] (ustma-ust tushganlari birlashtirilgan). */
  segments: [string, string][]
}

export interface LessonAttendance {
  lesson: Lesson
  host: { first_joined_at: string | null; names: string[]; segments: [string, string][] } | null
  students: AttendanceRow[]
  can_manage_students: boolean
}

export interface GroupStudentRow {
  id: number
  name: string
  ignored: boolean
  names: string[]
  lessons_attended: number
  minutes: number
  percent: number | null
  avg_late_minutes: number | null
  last_seen_at: string | null
}

export interface GroupStudents {
  group: GroupRef
  from: string
  to: string
  lessons_held: number
  students: GroupStudentRow[]
}
