import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { tGlobal } from '@/i18n/I18nProvider'
import { getLocale } from '@/i18n/locale'
import { BASE } from '@/lib/base'

/**
 * Backend bilan ishlash uchun yagona axios nusxasi.
 *
 * Frontend va backend bitta domenda ishlaydi (production'da nginx, ishlab
 * chiqishda Vite proksi), shuning uchun autentifikatsiya Sanctum'ning
 * cookie-sessiyasi orqali amalga oshadi: tokenlar localStorage'da saqlanmaydi,
 * CSRF himoyasi esa XSRF-TOKEN cookie'si orqali ishlaydi.
 */
export const api = axios.create({
  // "/api/..." so'rovlari ilova yo'liga nisbatan (dist.karsu.uz/zoom → /zoom/api/...).
  baseURL: BASE || '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let csrfPromise: Promise<unknown> | null = null

export function ensureCsrf(force = false) {
  if (force || !csrfPromise) {
    csrfPromise = api.get('/sanctum/csrf-cookie').catch((error) => {
      csrfPromise = null
      throw error
    })
  }
  return csrfPromise
}

const unsafeMethods = ['post', 'put', 'patch', 'delete']

api.interceptors.request.use(async (config) => {
  // Backend xabarlari (tekshiruv xatolari, Zoom xatolari) tanlangan tilda qaytadi.
  config.headers.set('X-Locale', getLocale())
  if (config.method && unsafeMethods.includes(config.method.toLowerCase())) {
    await ensureCsrf()
  }
  return config
})

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as RetriableConfig | undefined
    // 419 — CSRF tokeni eskirgan. Yangisini olib, so'rovni bir marta qaytaramiz.
    if (error.response?.status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      await ensureCsrf(true)
      return api.request(config)
    }
    return Promise.reject(error)
  },
)

export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Xatolikdan foydalanuvchiga ko'rsatiladigan matnni ajratib oladi. */
export function errorMessage(error: unknown, fallback?: string): string {
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as ApiErrorBody | undefined
    if (body?.errors) {
      const first = Object.values(body.errors)[0]?.[0]
      if (first) return first
    }
    const status = error.response?.status ?? 0
    // Production'da Laravel 500 xatoni faqat "Server Error" deb qaytaradi — foydalanuvchiga tushunarli matn.
    if (status >= 500 && (!body?.message || body.message === 'Server Error')) {
      return tGlobal('Serverda xatolik yuz berdi. Birozdan soʻng qayta urinib koʻring yoki administratorga xabar bering.')
    }
    if (body?.message) return body.message
    if (!error.response) return tGlobal('Server bilan aloqa yoʻq. Internet ulanishini tekshiring.')
    if (error.response.status === 403) return tGlobal('Bu amal uchun ruxsatingiz yoʻq.')
    if (error.response.status === 429) return tGlobal('Juda koʻp urinish. Birozdan soʻng qayta urinib koʻring.')
  }
  return fallback ?? tGlobal('Soʻrovni bajarib boʻlmadi. Qayta urinib koʻring.')
}

export function fieldErrors(error: unknown): Record<string, string> {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    const body = error.response.data as ApiErrorBody
    return Object.fromEntries(Object.entries(body.errors ?? {}).map(([key, value]) => [key, value[0]]))
  }
  return {}
}

export function isStatus(error: unknown, status: number): boolean {
  return axios.isAxiosError(error) && error.response?.status === status
}

/** Query parametrlari ichidan bo'sh qiymatlarni olib tashlaydi. */
export function cleanParams<T extends Record<string, unknown>>(params: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  ) as Partial<T>
}
