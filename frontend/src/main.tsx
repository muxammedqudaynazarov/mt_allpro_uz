import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'
import { AuthProvider, ME_KEY } from '@/auth/AuthProvider'
import { isStatus } from '@/lib/api'
import App from './App'
import './index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: true,
      retry: (count, error) => !isStatus(error, 401) && !isStatus(error, 403) && !isStatus(error, 404) && count < 2,
    },
  },
})

// Sessiya tugasa (401), foydalanuvchini login sahifasiga qaytaramiz.
queryClient.getQueryCache().subscribe((event) => {
  if (event.type === 'updated' && event.action.type === 'error' && isStatus(event.action.error, 401) && event.query.queryKey[0] !== 'me') {
    queryClient.setQueryData(ME_KEY, null)
  }
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <App />
        <Toaster position="top-center" richColors closeButton toastOptions={{ style: { fontFamily: 'var(--font-sans)' } }} />
      </AuthProvider>
    </QueryClientProvider>
  </StrictMode>,
)
