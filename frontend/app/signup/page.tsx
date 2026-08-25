'use client'

import { useState, useEffect } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Eye, EyeOff, Check } from 'lucide-react'
import { Logo } from '@/components/logo'
import { useRegisterMutation, useCurrentUser } from '@/lib/queries/auth'
import { Routes } from '@/lib/routes'

export default function SignUpPage() {
  const router = useRouter()
  const { data: currentUser, isLoading: isUserLoading } = useCurrentUser()
  const registerMutation = useRegisterMutation()
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [userType, setUserType] = useState<'photographer' | 'studio'>('photographer')
  const [formData, setFormData] = useState({
    fullName: '',
    username: '',
    email: '',
    password: '',
    confirmPassword: '',
    studioName: '',
    agreeToTerms: false
  })

  const [usernameStatus, setUsernameStatus] = useState<'idle' | 'checking' | 'available' | 'taken' | 'invalid'>('idle')
  const [usernameError, setUsernameError] = useState<string | null>(null)

  useEffect(() => {
    const username = formData.username.trim()
    if (username.length === 0) {
      setUsernameStatus('idle')
      setUsernameError(null)
      return
    }
    if (username.length < 3) {
      setUsernameStatus('invalid')
      setUsernameError('Username must be at least 3 characters')
      return
    }
    if (!/^[a-z0-9](?:[a-z0-9-]{0,48}[a-z0-9])?$/.test(username)) {
      setUsernameStatus('invalid')
      setUsernameError('Invalid format (hyphens must be internal)')
      return
    }

    setUsernameStatus('checking')
    setUsernameError(null)

    const timer = setTimeout(async () => {
      try {
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'
        const res = await fetch(`${baseUrl}/api/v1/auth/check-username?username=${encodeURIComponent(username)}`, {
          headers: { 'Accept': 'application/json' }
        })
        const data = await res.json()
        if (data.available) {
          setUsernameStatus('available')
          setUsernameError(null)
        } else {
          setUsernameStatus('taken')
          setUsernameError('Username is already taken or reserved')
        }
      } catch (err) {
        setUsernameStatus('idle')
      }
    }, 500)

    return () => clearTimeout(timer)
  }, [formData.username])

  useEffect(() => {
    if (!isUserLoading && currentUser?.user) {
      router.push(Routes.studioDashboard)
    }
  }, [currentUser, isUserLoading, router])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target
    if (name === 'username') {
      // Normalize: force lowercase and strip spaces/non-alphanumeric (except hyphens)
      const cleanValue = value.toLowerCase().replace(/[^a-z0-9-]/g, '')
      setFormData(prev => ({
        ...prev,
        username: cleanValue
      }))
      return
    }

    if (type === 'checkbox') {
      const { checked } = e.target as HTMLInputElement
      setFormData(prev => ({
        ...prev,
        [name]: checked
      }))
    } else {
      setFormData(prev => ({
        ...prev,
        [name]: value
      }))
    }
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
    
    if (usernameStatus !== 'available') {
      setErrorMsg(usernameError || 'Please choose an available username')
      return
    }

    // Validation
    if (formData.password !== formData.confirmPassword) {
      setErrorMsg('Passwords do not match')
      return
    }
    
    if (formData.password.length < 8) {
      setErrorMsg('Password must be at least 8 characters')
      return
    }

    if (!formData.agreeToTerms) {
      setErrorMsg('You must agree to the terms and conditions')
      return
    }

    registerMutation.mutate(
      {
        name: formData.fullName,
        username: formData.username,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.confirmPassword
      },
      {
        onSuccess: () => {
          router.push(Routes.studioDashboard)
        },
        onError: (err: any) => {
          setErrorMsg(err.message || 'Registration failed')
        }
      }
    )
  }

  const isLoading = registerMutation.isPending || isUserLoading

  const strength = passwordStrength()
  const strengthColors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500']
  const strengthLabels = ['Weak', 'Fair', 'Good', 'Strong']

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-secondary/20 flex items-center justify-center px-4 py-8">
      <div className="w-full max-w-2xl">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="flex justify-center mb-6">
            <Logo size="md" href="/" />
          </div>
          <h1 className="text-3xl font-bold text-foreground mb-2">Join ifotoset</h1>
          <p className="text-muted-foreground">Start for free. Upgrade whenever you're ready.</p>
        </div>

        {/* Form Card */}
        <div className="bg-card rounded-lg border border-border p-8 shadow-lg">
          <form onSubmit={handleSubmit} className="space-y-5">
            {errorMsg && (
              <div className="p-3 bg-red-500/10 border border-red-500/20 text-red-500 text-sm rounded-lg text-center">
                {errorMsg}
              </div>
            )}
            {/* User Type Selection */}
            <div>
              <label className="block text-sm font-medium text-foreground mb-3">I am a</label>
              <div className="grid grid-cols-2 gap-4">
                <button
                  type="button"
                  onClick={() => setUserType('photographer')}
                  className={`p-3 rounded-lg border-2 transition-all text-center font-medium ${
                    userType === 'photographer'
                      ? 'border-primary bg-primary/10 text-primary'
                      : 'border-border text-foreground hover:border-primary'
                  }`}
                >
                  Photographer
                </button>
                <button
                  type="button"
                  onClick={() => setUserType('studio')}
                  className={`p-3 rounded-lg border-2 transition-all text-center font-medium ${
                    userType === 'studio'
                      ? 'border-primary bg-primary/10 text-primary'
                      : 'border-border text-foreground hover:border-primary'
                  }`}
                >
                  Studio
                </button>
              </div>
            </div>

            {/* Full Name */}
            <div>
              <label htmlFor="fullName" className="block text-sm font-medium text-foreground mb-2">
                Full Name
              </label>
              <input
                type="text"
                id="fullName"
                name="fullName"
                value={formData.fullName}
                onChange={handleChange}
                placeholder="John Doe"
                required
                className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all"
              />
            </div>

            {/* Username */}
            <div>
              <label htmlFor="username" className="block text-sm font-medium text-foreground mb-2">
                Username (Subdomain)
              </label>
              <div className="relative">
                <input
                  type="text"
                  id="username"
                  name="username"
                  value={formData.username}
                  onChange={handleChange}
                  placeholder="yourusername"
                  required
                  className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all pr-36"
                />
                <div className="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-muted-foreground pointer-events-none select-none">
                  .{process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localtest.me:3000'}
                </div>
              </div>
              
              {usernameStatus === 'checking' && (
                <p className="text-xs text-muted-foreground mt-1 animate-pulse">Checking availability...</p>
              )}
              {usernameStatus === 'available' && (
                <p className="text-xs text-green-500 mt-1 flex items-center gap-1">
                  <Check size={14} /> Username is available!
                </p>
              )}
              {usernameStatus === 'taken' && (
                <p className="text-xs text-red-500 mt-1">{usernameError}</p>
              )}
              {usernameStatus === 'invalid' && (
                <p className="text-xs text-red-500 mt-1">{usernameError}</p>
              )}
              {formData.username && usernameStatus === 'available' && (
                <p className="text-xs text-muted-foreground mt-1.5 bg-secondary/30 p-2 rounded-md border border-border">
                  Your portfolio will be at:{' '}
                  <span className="font-semibold text-primary">
                    {formData.username.toLowerCase()}.{process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localtest.me:3000'}
                  </span>
                </p>
              )}
            </div>

            {/* Studio Name (conditional) */}
            {userType === 'studio' && (
              <div>
                <label htmlFor="studioName" className="block text-sm font-medium text-foreground mb-2">
                  Studio Name
                </label>
                <input
                  type="text"
                  id="studioName"
                  name="studioName"
                  value={formData.studioName}
                  onChange={handleChange}
                  placeholder="Your Studio Name"
                  required={userType === 'studio'}
                  className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all"
                />
              </div>
            )}

            {/* Email */}
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

            {/* Password */}
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-foreground mb-2">
                Password
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
              {formData.password && (
                <div className="space-y-2">
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

            {/* Confirm Password */}
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
                  className="w-full px-4 py-3 rounded-lg border border-border bg-input text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary transition-all pr-12"
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

            {/* Terms & Conditions */}
            <div className="flex items-start gap-3">
              <input
                type="checkbox"
                id="agreeToTerms"
                name="agreeToTerms"
                checked={formData.agreeToTerms}
                onChange={handleChange}
                className="w-4 h-4 rounded border-border cursor-pointer mt-1"
                required
              />
              <label htmlFor="agreeToTerms" className="text-sm text-foreground cursor-pointer">
                I agree to the{' '}
                <Link href="#" className="text-primary hover:text-accent">
                  Terms of Service
                </Link>{' '}
                and{' '}
                <Link href="#" className="text-primary hover:text-accent">
                  Privacy Policy
                </Link>
              </label>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isLoading}
              className="w-full py-3 px-4 bg-primary text-primary-foreground rounded-lg font-semibold hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isLoading ? 'Creating Account...' : 'Create Account'}
            </button>
          </form>

          {/* Sign In Link */}
          <div className="mt-6 text-center">
            <p className="text-muted-foreground">
              Already have an account?{' '}
              <Link href="/login" className="text-primary hover:text-accent font-semibold transition-colors">
                Sign in here
              </Link>
            </p>
          </div>
        </div>

        {/* Features */}
        <div className="mt-8 grid md:grid-cols-3 gap-4">
          {[
            { icon: '✓', title: 'Free Plan', desc: 'Free plan included. No credit card required.' },
            { icon: '🔒', title: 'Secure', desc: 'Enterprise-grade security' },
            { icon: '🚀', title: 'Easy Setup', desc: 'Get started in minutes' }
          ].map((feature, idx) => (
            <div key={idx} className="p-4 bg-card rounded-lg border border-border text-center">
              <div className="text-2xl mb-2">{feature.icon}</div>
              <p className="font-semibold text-foreground text-sm">{feature.title}</p>
              <p className="text-xs text-muted-foreground">{feature.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
