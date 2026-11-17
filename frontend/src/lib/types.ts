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
  /** Hozir darsda (faqat monitoring javobida). */
  present: number | null
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
  /** Dars o'tkazilayotgan Zoom host (email). */
  host: string | null
  /** Har haftalik jadvaldan yaratilgan bo'lsa — jadval ID. */
  series_id: number | null
  /** Zoom bulutiga avtomatik yozilmoqda. */
  record: boolean
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

export interface LessonOptionHost {
  id: number
  name: string
  email: string
  account: string | null
  /** Basic (litsenziyasiz) host'da uchrashuv 40 daqiqa bilan cheklangan. */
  licensed: boolean
  /** Tanlangan guruhning standart hosti. */
  default: boolean
  busy: boolean
  busy_until: string | null
}

export interface LessonOptions {
  now: string
  /** Tanlangan kun (YYYY-MM-DD). */
  date: string
  from: string
  to: string
  default_duration: number
  groups: LessonOptionGroup[]
  hosts: LessonOptionHost[]
  slots: LessonOptionSlot[]
}

export interface LiveParticipant {
  name: string
  email: string | null
  is_host: boolean
  present: boolean
  joined_at: string
  left_at: string | null
  minutes: number
}

export interface LiveSnapshot {
  /** webhook — Zoom hodisalari, dashboard — Zoom Dashboard API, none — ma'lumot yo'q. */
  source: 'webhook' | 'dashboard' | 'none'
  reason: string | null
  checked_at: string
  participants: LiveParticipant[]
}

export interface MonitorData {
  checked_at: string
  webhooks: boolean
  live: Lesson[]
  upcoming: Lesson[]
  finished: Lesson[]
}

export interface LessonSeries {
  id: number
  /** ISO hafta kuni: 1 — dushanba ... 7 — yakshanba. */
  weekday: number
  topic: string | null
  group: GroupRef
  teacher: UserRef
  slot: { id: number; name: string; starts_at: string; ends_at: string }
  host: { id: number; name: string; email: string } | null
  starts_on: string
  ends_on: string | null
  is_active: boolean
  cancelled_at: string | null
  next_date: string | null
  skips: string[]
  /** Bugungi uchrashuv yaratilmagan bo'lsa — sababi. */
  last_error: string | null
  can: { update: boolean; cancel: boolean; delete: boolean }
}

/** Haftalik darsning hali Zoom uchrashuvi yaratilmagan sanasi. */
export interface PlannedLesson {
  series_id: number
  date: string
  starts_at: string
  ends_at: string
  group: GroupRef
  teacher: UserRef
  slot: { id: number; name: string }
  topic: string | null
  skipped: boolean
  error: string | null
  can_cancel: boolean
}

export interface CalendarData {
  from: string
  to: string
  lead_minutes: number
  can_manage: boolean
  slots: { id: number; name: string; starts_at: string; ends_at: string }[]
  lessons: Lesson[]
  planned: PlannedLesson[]
}

export interface ZoomHost {
  id: number
  name: string
  email: string
  licensed: boolean
  is_active: boolean
  account: { id: number; name: string | null; is_active: boolean }
  open_lessons: number
}

export interface Group {
  id: number
  name: string
  hemis_id: string | null
  course: number
  is_active: boolean
  /** Standart host (ixtiyoriy). */
  zoom_user_email: string | null
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
  hosts_count: number
  has_webhook_secret: boolean
  /** Zoom ilovasining Event Subscriptions bo'limiga yoziladigan manzil. */
  webhook_url: string
  webhook_received_at: string | null
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

export interface Semester {
  id: number
  name: string
  starts_on: string
  ends_on: string
  current?: boolean
}

export type AssessmentLevel = 'past' | 'sust' | 'qoniqarli' | 'ortacha' | 'yaxshi' | 'alo'

export interface AssessmentCriteria {
  /** time — umumiy qatnashgan vaqt foizi, lessons — qatnashgan darslar foizi. */
  basis: 'time' | 'lessons'
  /** Talaba darsda kamida shuncha foiz bo'lsa — qatnashgan hisoblanadi. */
  presence_percent: number
  thresholds: Record<Exclude<AssessmentLevel, 'past'>, number>
}

export interface SemesterTotals {
  held: number
  missed: number
  pending: number
  zoom_minutes: number
  avg_lesson_minutes: number | null
  students: number
  avg_students_per_lesson: number | null
  avg_attendance_percent: number | null
}

export interface SemesterStudentRow {
  id: number
  name: string
  attended: number
  joined: number
  minutes: number
  percent_time: number | null
  percent_lessons: number | null
  avg_late_minutes: number | null
  level: AssessmentLevel | null
  level_label: string | null
  per_lesson: number[]
}

export interface SemesterGroupReport {
  semester: Semester
  group: GroupRef
  criteria: AssessmentCriteria
  totals: SemesterTotals
  levels: Record<AssessmentLevel, number>
  lessons: { id: number; starts_at: string; teacher: string; minutes: number; present: number }[]
  students: SemesterStudentRow[]
}

export interface SemesterOverview {
  semester: Semester | null
  criteria?: AssessmentCriteria
  groups?: { group: GroupRef; totals: SemesterTotals; levels: Record<AssessmentLevel, number> }[]
}

export interface RecordingSettings {
  enabled: boolean
  delete_from_zoom: boolean
  /** trash — Zoom savatiga (30 kun tiklash mumkin), delete — butunlay. */
  delete_action: 'trash' | 'delete'
  include_audio: boolean
}

export interface RecordingStorage {
  path: string
  used_bytes: number
  files: number
  pending: number
  failed: number
  /** Hali Zoom bulutida turgan (o'chirilmagan) yozuvlar hajmi. */
  zoom_bytes: number
  free_bytes: number | null
  min_free_gb: number
}

export interface Recording {
  id: number
  file_type: string
  recording_type: string | null
  extension: string | null
  size: number
  status: 'pending' | 'downloaded' | 'failed' | 'skipped'
  error: string | null
  attempts: number
  recording_start: string | null
  recording_end: string | null
  downloaded_at: string | null
  zoom_deleted_at: string | null
  url: string | null
  lesson?: { id: number; starts_at: string; group: string | null; teacher: string | null } | null
}

export interface SystemSettings {
  assessment: AssessmentCriteria
  assessment_defaults: AssessmentCriteria
  recording: RecordingSettings
  storage: RecordingStorage
}
