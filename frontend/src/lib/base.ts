/**
 * Ilova qaysi yo'lda ishlaydi: "/" (mt.regofis.uz) yoki "/zoom" (dist.karsu.uz/zoom).
 * Yig'ishda VITE_BASE_PATH orqali beriladi (vite.config.ts → base).
 */
export const BASE = import.meta.env.BASE_URL.replace(/\/$/, '')

/** "/api/..." → "/zoom/api/..." (bazaviy yo'l bo'lmasa — o'zgarishsiz). */
export function withBase(path: string): string {
  return `${BASE}${path.startsWith('/') ? path : `/${path}`}`
}
