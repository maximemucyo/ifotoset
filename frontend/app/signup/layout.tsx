import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Create Account | ifotoset',
  description: 'Sign up for ifotoset to build your photography portfolio, deliver private client galleries, and manage your business.',
}

export default function SignupLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return children
}
