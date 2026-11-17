import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Ishlab chiqishda (npm run dev) /api, /auth va /sanctum so'rovlari Laravel'ga
// proksi qilinadi. Shunda brauzer uchun frontend va backend bitta domenda bo'ladi
// va Sanctum cookie-sessiyasi CORS'siz ishlaydi — xuddi production'dagidek.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backend = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'
  // Ilova domen ildizida emas, yo'lda ishlasa (masalan, dist.karsu.uz/zoom): VITE_BASE_PATH=/zoom/
  const base = `/${(env.VITE_BASE_PATH || '/').replace(/^\/+|\/+$/g, '')}/`.replace(/^\/\/$/, '/')
  const prefix = base.replace(/\/$/, '')
  // Proksi production'dagi nginx kabi: prefiksni olib tashlab, Laravel'ga X-Forwarded-Prefix bilan uzatadi.
  const target = { target: backend, changeOrigin: false, xfwd: true, headers: prefix ? { 'X-Forwarded-Prefix': prefix } : {}, rewrite: (path: string) => path.slice(prefix.length) }
  const proxy = Object.fromEntries(['/api/', '/auth/', '/sanctum/', '/l/'].map((path) => [`${prefix}${path}`, target]))

  return {
    base,
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      proxy,
    },
    preview: {
      port: 4173,
      proxy,
    },
    build: {
      sourcemap: false,
      chunkSizeWarningLimit: 800,
    },
  }
})
