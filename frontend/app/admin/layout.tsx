import { AdminLayoutShell } from '@/components/admin-layout-shell'
import { AuthGuard } from '@/components/auth-guard'

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <AuthGuard>
      <AdminLayoutShell>
        {children}
      </AdminLayoutShell>
    </AuthGuard>
  )
}
