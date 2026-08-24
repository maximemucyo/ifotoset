import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Forgot Password | ifotoset',
  description: 'Reset your ifotoset account password.',
}

export default function ForgotPasswordLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return children
}
