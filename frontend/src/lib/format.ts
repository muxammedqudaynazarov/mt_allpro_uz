/**
 * Sana va vaqtni formatlash. Universitet Toshkent vaqti bo'yicha ishlaydi,
 * shuning uchun foydalanuvchi qaysi vaqt mintaqasida bo'lmasin, vaqtlar
 * Asia/Tashkent bo'yicha ko'rsatiladi.
 */
import { getLocale } from '@/i18n/locale'

export const TIME_ZONE = 'Asia/Tashkent'

const NAMES = {
  uz: {
    months: ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'],
    weekdays: ['yakshanba', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba'],
    greetings: ['Xayrli tun', 'Xayrli tong', 'Xayrli kun', 'Xayrli kech'],
    hour: 'soat',
    minute: 'daqiqa',
  },
  kaa: {
    months: ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'],
    weekdays: ['ekshembi', 'dúyshembi', 'siyshembi', 'sárshembi', 'piyshembi', 'juma', 'shembi'],
    greetings: 'Assalawma áleykum',
    hour: 'saat',
    minute: 'minut',
  },
} as const

const names = () => NAMES[getLocale()]
const month = (index: number) => names().months[index]

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
  return `${p.day}-${month(p.month - 1)}`
}

/** 16-noyabr, 2026 */
export function formatDate(value: string | Date): string {
  const p = parts(value)
  return `${p.day}-${month(p.month - 1)}, ${p.year}`
}

/** 16-noyabr, 14:30 (joriy yil bo'lsa yil ko'rsatilmaydi) */
export function formatDateTime(value: string | Date): string {
  const p = parts(value)
  const now = parts(new Date())
  const date = p.year === now.year ? `${p.day}-${month(p.month - 1)}` : `${p.day}-${month(p.month - 1)}, ${p.year}`
  return `${date}, ${p.hour}:${p.minute}`
}

/** dushanba, 16-noyabr */
export function formatLongToday(value: string | Date = new Date()): string {
  const p = parts(value)
  return `${names().weekdays[p.weekday]}, ${p.day}-${month(p.month - 1)}`
}

/** dushanba */
export function formatWeekday(value: string | Date): string {
  return names().weekdays[parts(value).weekday]
}

/** ISO hafta kuni nomi: 1 — dushanba ... 7 — yakshanba. */
export function weekdayName(iso: number): string {
  return names().weekdays[iso % 7]
}

/** ISO hafta kuni (Toshkent bo'yicha): 1 — dushanba ... 7 — yakshanba. */
export function isoWeekday(value: string | Date): number {
  return parts(value).weekday || 7
}

/** YYYY-MM-DD sanasini Toshkent kunining o'rtasi sifatida (sana hisoblari uchun). */
export function dayDate(iso: string): Date {
  return new Date(`${iso}T12:00:00+05:00`)
}

/** Berilgan kun joylashgan haftaning dushanbasi (YYYY-MM-DD). */
export function weekStart(iso: string): string {
  const date = dayDate(iso)
  return isoDate(addDays(date, 1 - isoWeekday(date)))
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

/** Salomlashish: o'zbekchada kun vaqtiga qarab, qoraqalpoqchada doim "Assalawma áleykum". */
export function greeting(date: Date = new Date()): string {
  const greetings = names().greetings
  if (typeof greetings === 'string') return greetings
  const hour = Number(parts(date).hour)
  const [night, morning, day, evening] = greetings
  if (hour < 5) return night
  if (hour < 12) return morning
  if (hour < 18) return day
  return evening
}

/** 1 soat 20 daqiqa / 45 daqiqa (qoraqalpoqchada: 1 saat 20 minut) */
export function formatDuration(minutes: number): string {
  const total = Math.max(0, Math.round(minutes))
  const hours = Math.floor(total / 60)
  const rest = total % 60
  const { hour, minute } = names()
  if (hours && rest) return `${hours} ${hour} ${rest} ${minute}`
  if (hours) return `${hours} ${hour}`
  return `${rest} ${minute}`
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
