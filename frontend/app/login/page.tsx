'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Eye, EyeOff } from 'lucide-react'
import { Logo } from '@/components/logo'
import { useLoginMutation, useCurrentUser } from '@/lib/queries/auth'
import { Routes } from '@/lib/routes'

export default function LoginPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [verifiedError, setVerifiedError] = useState<string | null>(null)

  useEffect(() => {
    if (searchParams && searchParams.get('verified') === '0') {
      const err = searchParams.get('error')
      if (err === 'expired') {
        setVerifiedError('Your verification link has expired. Please sign in to request a new link.')
      } else {
        setVerifiedError('The verification link is invalid or has expired. Please sign in to request a new link.')
      }
      const newUrl = window.location.pathname
      window.history.replaceState({}, '', newUrl)
    }
  }, [searchParams])
  const { data: currentUser, isLoading: isUserLoading } = useCurrentUser()
  const loginMutation = useLoginMutation()
  const [showPassword, setShowPassword] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    remember: false
  })

  useEffect(() => {
    if (!isUserLoading && currentUser?.user) {
      if (currentUser.permissions?.includes('admin.access')) {
        router.replace(Routes.adminDashboard)
      } else {
        router.replace(Routes.studioDashboard)
      }
    }
  }, [currentUser, isUserLoading, router])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value, type, checked } = e.target
    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value
    }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg(null)
    
    loginMutation.mutate(
      {
        email: formData.email,
        password: formData.password,
        remember: formData.remember
      },
      {
        onSuccess: (profile) => {
          // Use window.location.href for a hard navigation after login.
          // router.replace() can be silently cancelled by AuthGuard's own
          // redirect logic in Next.js App Router, causing an infinite loop.
          // A hard navigation guarantees we land on the dashboard and the
          // session cookies are re-validated from scratch.
          const destination = profile.permissions?.includes('admin.access')
            ? Routes.adminDashboard
            : Routes.studioDashboard
          window.location.href = destination
        },
        onError: (err: any) => {
          setErrorMsg(err.message || 'Invalid email or password')
        }
      }
    )
  }

  const isLoading = loginMutation.isPending

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-secondary/20 flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="flex justify-center mb-6">
            <Logo size="md" href="/" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Welcome Back</h1>
          <p className="text-muted-foreground">Sign in to your account to continue</p>
        </div>

        {/* Form Card */}
        <div className="bg-card rounded-lg border border-border p-8 shadow-lg">
          <form onSubmit={handleSubmit} className="space-y-5">
            {verifiedError && (
              <div className="p-3 bg-red-500/10 border border-red-500/20 text-red-500 text-sm rounded-lg text-center font-medium">
                {verifiedError}
              </div>
            )}
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
              <input
                type="email"
                id="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                placeholder="you@example.com"
                required
                className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all"
              />
            </div>

            {/* Password Field */}
            <div>
              <div className="flex items-center justify-between mb-2">
                <label htmlFor="password" className="block text-sm font-medium text-foreground">
                  Password
                </label>
                <Link href="/forgot-password" className="text-sm text-primary hover:text-accent transition-colors">
                  Forgot?
                </Link>
              </div>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  id="password"
                  name="password"
                  value={formData.password}
                  onChange={handleChange}
                  placeholder="••••••••"
                  required
                  className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all pr-12"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                >
                  {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                </button>
              </div>
            </div>

            {/* Remember Me */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="remember"
                name="remember"
                checked={formData.remember}
                onChange={handleChange}
                className="w-4 h-4 rounded border-border cursor-pointer"
              />
              <label htmlFor="remember" className="ml-2 text-sm text-foreground cursor-pointer">
                Remember me
              </label>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isLoading}
              className="w-full py-3 px-4 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? 'Signing in...' : 'Sign In'}
            </button>
          </form>

          {/* Divider */}
          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-border"></div>
            </div>
            <div className="relative flex justify-center text-sm">
              <span className="px-2 bg-card text-muted-foreground">or</span>
            </div>
          </div>

          {/* Sign Up Link */}
          <div className="text-center">
            <p className="text-muted-foreground">
              Don&apos;t have an account?{' '}
              <Link href="/signup" className="text-primary hover:text-accent font-semibold transition-colors">
                Sign up here
              </Link>
            </p>
          </div>
        </div>


      </div>
    </div>
  )
}
