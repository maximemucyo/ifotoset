import { Providers } from '@/components/providers'
import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Book a Photography Session',
}

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <Providers>
      {children}
    </Providers>
  )
}
