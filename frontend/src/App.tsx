import { lazy, Suspense } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router'
import { GuestOnly, RequireAuth, RequireRole } from '@/auth/guards'
import { AppShell } from '@/components/layout/AppShell'
import { FullPageLoader, SectionLoader } from '@/components/ui/Spinner'

const LoginPage = lazy(() => import('@/pages/LoginPage'))
const PendingPage = lazy(() => import('@/pages/PendingPage'))
const DashboardPage = lazy(() => import('@/pages/DashboardPage'))
const CreateLessonPage = lazy(() => import('@/pages/CreateLessonPage'))
const LessonsPage = lazy(() => import('@/pages/LessonsPage'))
const LessonDetailPage = lazy(() => import('@/pages/LessonDetailPage'))
const GroupStudentsPage = lazy(() => import('@/pages/GroupStudentsPage'))
const UsersPage = lazy(() => import('@/pages/UsersPage'))
const GroupsPage = lazy(() => import('@/pages/GroupsPage'))
const ReportsPage = lazy(() => import('@/pages/ReportsPage'))
const ZoomAccountsPage = lazy(() => import('@/pages/ZoomAccountsPage'))
const SlotsPage = lazy(() => import('@/pages/SlotsPage'))
const AuditPage = lazy(() => import('@/pages/AuditPage'))
const ProfilePage = lazy(() => import('@/pages/ProfilePage'))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'))

export default function App() {
  return (
    <BrowserRouter>
      <Suspense fallback={<FullPageLoader />}>
        <Routes>
          <Route
            path="/login"
            element={
              <GuestOnly>
                <LoginPage />
              </GuestOnly>
            }
          />
          <Route element={<RequireAuth />}>
            <Route path="/pending" element={<PendingPage />} />
            <Route
              element={
                <Suspense fallback={<SectionLoader />}>
                  <AppShell />
                </Suspense>
              }
            >
              <Route index element={<DashboardPage />} />
              <Route path="lessons" element={<LessonsPage />} />
              <Route element={<RequireRole roles={['teacher', 'super_admin']} />}>
                <Route path="lessons/new" element={<CreateLessonPage />} />
              </Route>
              <Route path="lessons/:id" element={<LessonDetailPage />} />
              <Route path="profile" element={<ProfilePage />} />
              <Route element={<RequireRole roles={['education', 'super_admin']} />}>
                <Route path="users" element={<UsersPage />} />
                <Route path="groups" element={<GroupsPage />} />
                <Route path="groups/:id/students" element={<GroupStudentsPage />} />
                <Route path="reports" element={<ReportsPage />} />
              </Route>
              <Route element={<RequireRole roles={['super_admin']} />}>
                <Route path="zoom-accounts" element={<ZoomAccountsPage />} />
                <Route path="slots" element={<SlotsPage />} />
                <Route path="audit" element={<AuditPage />} />
              </Route>
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </Suspense>
    </BrowserRouter>
  )
}
