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
  const proxy = { target: backend, changeOrigin: false, xfwd: true }

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      proxy: { '/api': proxy, '/auth': proxy, '/sanctum': proxy },
    },
    preview: {
      port: 4173,
      proxy: { '/api': proxy, '/auth': proxy, '/sanctum': proxy },
    },
    build: {
      sourcemap: false,
      chunkSizeWarningLimit: 800,
    },
  }
})
