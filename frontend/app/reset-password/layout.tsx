import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Reset Password | ifotoset',
  description: 'Enter your new password to reset your ifotoset account.',
}

export default function ResetPasswordLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return children
}
