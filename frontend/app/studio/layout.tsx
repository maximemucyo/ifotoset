import { StudioSidebar } from '@/components/studio-sidebar'
import { AuthGuard } from '@/components/auth-guard'

export default function StudioLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <AuthGuard>
      <div className="flex min-h-screen bg-background">
        <StudioSidebar />
        {children}
      </div>
    </AuthGuard>
  )
}
