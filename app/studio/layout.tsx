import { StudioSidebar } from '@/components/studio-sidebar'

export default function StudioLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <div className="flex min-h-screen bg-background">
      <StudioSidebar />
      {children}
    </div>
  )
}
