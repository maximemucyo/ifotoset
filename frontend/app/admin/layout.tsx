import { AdminSidebar } from '@/components/admin-sidebar'
import { AuthGuard } from '@/components/auth-guard'

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <AuthGuard>
      <div className="flex min-h-screen bg-background">
        <AdminSidebar />
        {children}
      </div>
    </AuthGuard>
  )
}
