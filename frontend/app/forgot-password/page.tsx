'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Mail, ArrowLeft, CheckCircle2 } from 'lucide-react'
import { Logo } from '@/components/logo'
import { useForgotPasswordMutation, useCurrentUser } from '@/lib/queries/auth'
import { Routes } from '@/lib/routes'

export default function ForgotPasswordPage() {
  const router = useRouter()
  const { data: currentUser, isLoading: isUserLoading } = useCurrentUser()
  const forgotPasswordMutation = useForgotPasswordMutation()
  const [email, setEmail] = useState('')
  const [successMsg, setSuccessMsg] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  // Redirect to dashboard if user is already logged in
  useEffect(() => {
    if (!isUserLoading && currentUser?.user) {
      if (currentUser.permissions?.includes('admin.access')) {
        router.replace(Routes.adminDashboard)
      } else {
        router.replace(Routes.studioDashboard)
      }
    }
  }, [currentUser, isUserLoading, router])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg(null)
    setSuccessMsg(null)

    forgotPasswordMutation.mutate(email, {
      onSuccess: (data) => {
        setSuccessMsg(data.message || 'If an account exists for that email, a password reset link has been sent.')
      },
      onError: (err: any) => {
        setErrorMsg(err.message || 'An unexpected error occurred. Please try again.')
      }
    })
  }

  const isLoading = forgotPasswordMutation.isPending

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-secondary/20 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="flex justify-center mb-6">
            <Logo size="md" href="/" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Forgot Password</h1>
          <p className="text-muted-foreground">
            Enter your email address and we&apos;ll send you a link to reset your password.
          </p>
        </div>

        {/* Card */}
        <div className="bg-card rounded-lg border border-border p-8 shadow-lg relative overflow-hidden">
          {/* Glowing background item */}
          <div className="absolute inset-0 -z-10 bg-gradient-to-tr from-primary/5 to-transparent blur-2xl rounded-2xl" />

          {successMsg ? (
            <div className="text-center py-4">
              <div className="flex justify-center mb-4 text-green-500">
                <CheckCircle2 size={48} />
              </div>
              <h3 className="text-xl font-semibold text-foreground mb-3">Reset Link Sent</h3>
              <p className="text-muted-foreground mb-6 text-sm leading-relaxed">
                {successMsg}
              </p>
              <Link
                href={Routes.login}
                className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-accent transition-colors"
              >
                <ArrowLeft size={16} /> Back to Sign In
              </Link>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-5">
              {errorMsg && (
                <div className="p-3 bg-red-500/10 border border-red-500/20 text-red-500 text-sm rounded-lg text-center">
                  {errorMsg}
                </div>
              )}

              {/* Email Field */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-foreground mb-2">
                  Email Address
                </label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">
                    <Mail size={18} />
                  </span>
                  <input
                    type="email"
                    id="email"
                    name="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="you@example.com"
                    required
                    disabled={isLoading}
                    className="w-full pl-11 pr-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all disabled:opacity-50"
                  />
                </div>
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                disabled={isLoading}
                className="w-full py-3 px-4 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {isLoading ? (
                  <>
                    <span className="animate-spin border-2 border-primary-foreground border-t-transparent rounded-full h-4 w-4" />
                    Sending Link...
                  </>
                ) : (
                  'Send Reset Link'
                )}
              </button>

              {/* Back to Login Link */}
              <div className="text-center pt-2">
                <Link
                  href={Routes.login}
                  className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                  <ArrowLeft size={16} /> Back to Sign In
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
