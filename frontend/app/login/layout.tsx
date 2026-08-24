import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Sign In | ifotoset',
  description: 'Sign in to your ifotoset account to manage your photography portfolio, deliver client galleries, and view bookings.',
}

export default function LoginLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return children
}
