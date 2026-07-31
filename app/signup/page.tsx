'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Eye, EyeOff, Check } from 'lucide-react'
import { Logo } from '@/components/logo'

export default function SignUpPage() {
  const router = useRouter()
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [userType, setUserType] = useState<'photographer' | 'studio'>('photographer')
  const [formData, setFormData] = useState({
    fullName: '',
    email: '',
    password: '',
    confirmPassword: '',
    studioName: '',
    agreeToTerms: false
  })

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target
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
    
    // Validation
    if (formData.password !== formData.confirmPassword) {
      alert('Passwords do not match')
      return
    }
    
    if (formData.password.length < 6) {
      alert('Password must be at least 6 characters')
      return
    }

    if (!formData.agreeToTerms) {
      alert('You must agree to the terms and conditions')
      return
    }

    setIsLoading(true)
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500))
    
    // Store mock session
    localStorage.setItem('user_email', formData.email)
    localStorage.setItem('user_name', formData.fullName)
    localStorage.setItem('user_type', userType)
    localStorage.setItem('is_authenticated', 'true')
    
    // Redirect to studio dashboard
    router.push('/studio/dashboard')
    
    setIsLoading(false)
  }

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
          <p className="text-muted-foreground">Create your free account and start building your portfolio</p>
        </div>

        {/* Form Card */}
        <div className="bg-card rounded-lg border border-border p-8 shadow-lg">
          <form onSubmit={handleSubmit} className="space-y-5">
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
            { icon: '✓', title: 'Free Trial', desc: '30-day free trial, no card required' },
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
