import { StudioLayoutShell } from '@/components/studio-layout-shell'
import { AuthGuard } from '@/components/auth-guard'

export default function StudioLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <AuthGuard>
      <StudioLayoutShell>
        {children}
      </StudioLayoutShell>
    </AuthGuard>
  )
}
