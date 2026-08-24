import { AdminLayoutShell } from '@/components/admin-layout-shell'
import { AuthGuard } from '@/components/auth-guard'
import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: {
    template: '%s | Admin | ifotoset',
    default: 'Admin Dashboard | ifotoset',
  },
  robots: {
    index: false,
    follow: false,
  },
}

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
