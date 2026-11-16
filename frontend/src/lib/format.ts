/**
 * Sana va vaqtni formatlash. Universitet Toshkent vaqti bo'yicha ishlaydi,
 * shuning uchun foydalanuvchi qaysi vaqt mintaqasida bo'lmasin, vaqtlar
 * Asia/Tashkent bo'yicha ko'rsatiladi.
 */
export const TIME_ZONE = 'Asia/Tashkent'

const MONTHS = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr']
const WEEKDAYS = ['yakshanba', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba']

interface Parts {
  year: number
  month: number
  day: number
  hour: string
  minute: string
  weekday: number
}

const partsFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: TIME_ZONE,
  year: 'numeric',
  month: 'numeric',
  day: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
  weekday: 'short',
})

const WEEKDAY_INDEX: Record<string, number> = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }

function parts(value: string | Date): Parts {
  const date = typeof value === 'string' ? new Date(value) : value
  const map: Record<string, string> = {}
  for (const part of partsFormatter.formatToParts(date)) map[part.type] = part.value
  return {
    year: Number(map.year),
    month: Number(map.month),
    day: Number(map.day),
    hour: map.hour,
    minute: map.minute,
    weekday: WEEKDAY_INDEX[map.weekday] ?? 0,
  }
}

/** 14:30 */
export function formatTime(value: string | Date): string {
  const p = parts(value)
  return `${p.hour}:${p.minute}`
}

/** 16-noyabr */
export function formatDayMonth(value: string | Date): string {
  const p = parts(value)
  return `${p.day}-${MONTHS[p.month - 1]}`
}

/** 16-noyabr, 2026 */
export function formatDate(value: string | Date): string {
  const p = parts(value)
  return `${p.day}-${MONTHS[p.month - 1]}, ${p.year}`
}

/** 16-noyabr, 14:30 (joriy yil bo'lsa yil ko'rsatilmaydi) */
export function formatDateTime(value: string | Date): string {
  const p = parts(value)
  const now = parts(new Date())
  const date = p.year === now.year ? `${p.day}-${MONTHS[p.month - 1]}` : `${p.day}-${MONTHS[p.month - 1]}, ${p.year}`
  return `${date}, ${p.hour}:${p.minute}`
}

/** dushanba, 16-noyabr */
export function formatLongToday(value: string | Date = new Date()): string {
  const p = parts(value)
  return `${WEEKDAYS[p.weekday]}, ${p.day}-${MONTHS[p.month - 1]}`
}

export function isSameDay(a: string | Date, b: string | Date): boolean {
  const pa = parts(a)
  const pb = parts(b)
  return pa.year === pb.year && pa.month === pb.month && pa.day === pb.day
}

/** YYYY-MM-DD (Toshkent bo'yicha) — sana filtrlari uchun. */
export function isoDate(value: string | Date = new Date()): string {
  const p = parts(value)
  return `${p.year}-${String(p.month).padStart(2, '0')}-${String(p.day).padStart(2, '0')}`
}

export function addDays(date: Date, days: number): Date {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + days)
  return copy
}

/** Kun vaqtiga qarab salomlashish. */
export function greeting(date: Date = new Date()): string {
  const hour = Number(parts(date).hour)
  if (hour < 5) return 'Xayrli tun'
  if (hour < 12) return 'Xayrli tong'
  if (hour < 18) return 'Xayrli kun'
  return 'Xayrli kech'
}

/** 1 soat 20 daqiqa / 45 daqiqa */
export function formatDuration(minutes: number): string {
  const total = Math.max(0, Math.round(minutes))
  const hours = Math.floor(total / 60)
  const rest = total % 60
  if (hours && rest) return `${hours} soat ${rest} daqiqa`
  if (hours) return `${hours} soat`
  return `${rest} daqiqa`
}

/** Hozirdan berilgan vaqtgacha qolgan daqiqalar (manfiy bo'lishi mumkin). */
export function minutesUntil(value: string, now: number = Date.now()): number {
  return Math.round((new Date(value).getTime() - now) / 60000)
}

export function plural(count: number, word: string): string {
  return `${count.toLocaleString('ru-RU')} ${word}`
}

/** Ism-familiyadan bosh harflar (avatar uchun). */
export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0]?.toLocaleUpperCase('uz') ?? '')
    .join('')
}
