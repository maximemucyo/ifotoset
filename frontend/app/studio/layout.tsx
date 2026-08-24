import { StudioLayoutShell } from '@/components/studio-layout-shell'
import { AuthGuard } from '@/components/auth-guard'
import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: {
    template: '%s | Studio | ifotoset',
    default: 'Studio Dashboard | ifotoset',
  },
  robots: {
    index: false,
    follow: false,
  },
}

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
