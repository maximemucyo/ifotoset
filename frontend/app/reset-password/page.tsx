'use client'

import { Suspense, useState, useEffect } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { Eye, EyeOff, Check, AlertTriangle, CheckCircle2, ArrowRight } from 'lucide-react'
import { Logo } from '@/components/logo'
import { useResetPasswordMutation, useCurrentUser } from '@/lib/queries/auth'
import { Routes } from '@/lib/routes'

function ResetPasswordForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { data: currentUser, isLoading: isUserLoading } = useCurrentUser()
  const resetPasswordMutation = useResetPasswordMutation()

  const token = searchParams.get('token')
  const email = searchParams.get('email')

  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [successMsg, setSuccessMsg] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [formData, setFormData] = useState({
    password: '',
    confirmPassword: ''
  })

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

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target
    setFormData(prev => ({
      ...prev,
      [name]: value
    }))
  }

  const passwordStrength = () => {
    const pwd = formData.password
    let strength = 0
    if (pwd.length >= 8) strength++
    if (/[A-Z]/.test(pwd)) strength++
    if (/[0-9]/.test(pwd)) strength++
    if (/[^A-Za-z0-9]/.test(pwd)) strength++
    return strength
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg(null)
    setSuccessMsg(null)

    if (!token || !email) {
      setErrorMsg('This password reset link is invalid or incomplete.')
      return
    }

    if (formData.password !== formData.confirmPassword) {
      setErrorMsg('Passwords do not match.')
      return
    }

    if (formData.password.length < 8) {
      setErrorMsg('Password must be at least 8 characters.')
      return
    }

    resetPasswordMutation.mutate(
      {
        token,
        email,
        password: formData.password,
        password_confirmation: formData.confirmPassword
      },
      {
        onSuccess: (data) => {
          setSuccessMsg(data.message || 'Your password has been reset successfully.')
        },
        onError: (err: any) => {
          setErrorMsg(err.message || 'This password reset link is invalid or has expired.')
        }
      }
    )
  }

  const isLoading = resetPasswordMutation.isPending || isUserLoading
  const strength = passwordStrength()
  const strengthColors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500']
  const strengthLabels = ['Weak', 'Fair', 'Good', 'Strong']

  // If parameters are missing, render the malformed link view
  if (!token || !email) {
    return (
      <div className="text-center py-6">
        <div className="flex justify-center mb-4 text-red-500">
          <AlertTriangle size={48} />
        </div>
        <h3 className="text-xl font-semibold text-foreground mb-3">Invalid Reset Link</h3>
        <p className="text-muted-foreground mb-8 text-sm leading-relaxed">
          This password reset link is invalid, incomplete, or has expired. Please request a new link.
        </p>
        <Link
          href={Routes.forgotPassword}
          className="inline-flex items-center justify-center w-full py-3 px-4 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors"
        >
          Request a New Link
        </Link>
      </div>
    )
  }

  if (successMsg) {
    return (
      <div className="text-center py-6">
        <div className="flex justify-center mb-4 text-green-500">
          <CheckCircle2 size={48} />
        </div>
        <h3 className="text-xl font-semibold text-foreground mb-3">Password Reset Successful</h3>
        <p className="text-muted-foreground mb-8 text-sm leading-relaxed font-normal">
          Your password has been updated. You can now sign in with your new credentials.
        </p>
        <Link
          href={Routes.login}
          className="inline-flex items-center justify-center gap-2 w-full py-3 px-4 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors"
        >
          Sign In Now <ArrowRight size={16} />
        </Link>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      {errorMsg && (
        <div className="p-4 bg-red-500/10 border border-red-500/20 text-red-500 text-sm rounded-lg flex flex-col gap-3 items-center text-center">
          <span>{errorMsg}</span>
          <Link
            href={Routes.forgotPassword}
            className="text-xs font-semibold text-primary hover:text-accent underline transition-colors"
          >
            Request a new link
          </Link>
        </div>
      )}

      {/* Password Field */}
      <div>
        <label htmlFor="password" className="block text-sm font-medium text-foreground mb-2">
          New Password
        </label>
        <div className="relative mb-2">
          <input
            type={showPassword ? 'text' : 'password'}
            id="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            placeholder="••••••••"
            required
            disabled={isLoading}
            className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all pr-12 disabled:opacity-50"
          />
          <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
          >
            {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
          </button>
        </div>
        {formData.password && (
          <div className="space-y-2 mt-2">
            <div className="flex gap-1">
              {[0, 1, 2, 3].map(i => (
                <div
                  key={i}
                  className={`h-2 flex-1 rounded-full transition-colors ${
                    i < strength ? strengthColors[strength - 1] : 'bg-muted'
                  }`}
                />
              ))}
            </div>
            <p className="text-xs text-muted-foreground">
              Password strength: <span className="font-medium">{strengthLabels[strength - 1] || 'Very Weak'}</span>
            </p>
          </div>
        )}
      </div>

      {/* Confirm Password Field */}
      <div>
        <label htmlFor="confirmPassword" className="block text-sm font-medium text-foreground mb-2">
          Confirm Password
        </label>
        <div className="relative">
          <input
            type={showConfirmPassword ? 'text' : 'password'}
            id="confirmPassword"
            name="confirmPassword"
            value={formData.confirmPassword}
            onChange={handleChange}
            placeholder="••••••••"
            required
            disabled={isLoading}
            className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all pr-12 disabled:opacity-50"
          />
          <button
            type="button"
            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
            className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
          >
            {showConfirmPassword ? <EyeOff size={20} /> : <Eye size={20} />}
          </button>
        </div>
        {formData.confirmPassword && formData.password === formData.confirmPassword && (
          <p className="text-sm text-green-500 mt-1 flex items-center gap-1">
            <Check size={16} /> Passwords match
          </p>
        )}
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
            Resetting Password...
          </>
        ) : (
          'Reset Password'
        )}
      </button>
    </form>
  )
}

export default function ResetPasswordPage() {
  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-secondary/20 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-md">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="flex justify-center mb-6">
            <Logo size="md" href="/" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Reset Password</h1>
          <p className="text-muted-foreground">
            Enter your new password below to complete the password reset.
          </p>
        </div>

        {/* Card */}
        <div className="bg-card rounded-lg border border-border p-8 shadow-lg relative overflow-hidden">
          {/* Glowing background item */}
          <div className="absolute inset-0 -z-10 bg-gradient-to-tr from-primary/5 to-transparent blur-2xl rounded-2xl" />

          <Suspense
            fallback={
              <div className="flex flex-col items-center justify-center py-12 gap-4">
                <span className="animate-spin border-4 border-primary border-t-transparent rounded-full h-8 w-8" />
                <p className="text-sm text-muted-foreground">Verifying link details...</p>
              </div>
            }
          >
            <ResetPasswordForm />
          </Suspense>
        </div>
      </div>
    </div>
  )
}
