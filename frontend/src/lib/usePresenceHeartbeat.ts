import { useEffect } from 'react'
import { api } from './api'

/**
 * Sayt ochiq va ko'rinib turganda har daqiqada "ping" — foydalanuvchi onlayn ko'rinadi va
 * kirish tarixiga yoziladi (Foydalanuvchilar sahifasi). Yashirin vkladkada yuborilmaydi.
 */
export function usePresenceHeartbeat(intervalMs = 60_000) {
  useEffect(() => {
    const ping = () => {
      if (document.visibilityState === 'visible') api.get('/api/auth/ping').catch(() => undefined)
    }
    const timer = window.setInterval(ping, intervalMs)
    document.addEventListener('visibilitychange', ping)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', ping)
    }
  }, [intervalMs])
}
