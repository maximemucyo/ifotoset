'use client'

import { useState, useEffect } from 'react'
import { usePathname } from 'next/navigation'
import { StudioSidebar } from './studio-sidebar'
import { MobileTopBar } from './mobile-top-bar'
import { useCurrentUser } from '@/lib/queries/auth'
import { authFetch } from '@/lib/auth'
import { AlertTriangle, RefreshCw } from 'lucide-react'

interface StudioLayoutShellProps {
  children: React.ReactNode
}

export function StudioLayoutShell({ children }: StudioLayoutShellProps) {
  const [isOpen, setIsOpen] = useState(false)
  const pathname = usePathname()
  
  const { data: currentUser } = useCurrentUser()
  const [resending, setResending] = useState(false)
  const [resendCountdown, setResendCountdown] = useState(0)
  const [resendMsg, setResendMsg] = useState<{ type: 'success' | 'error', text: string } | null>(null)

  useEffect(() => {
    if (resendCountdown <= 0) return
    const timer = setTimeout(() => {
      setResendCountdown(prev => prev - 1)
    }, 1000)
    return () => clearTimeout(timer)
  }, [resendCountdown])

  const handleResendVerification = async () => {
    if (resendCountdown > 0 || resending) return

    setResending(true)
    setResendMsg(null)
    try {
      const res = await authFetch<{ message: string }>('/auth/resend-verification', {
        method: 'POST'
      })
      setResendMsg({ type: 'success', text: res.message || 'Verification link sent!' })
      setResendCountdown(60)
    } catch (err: any) {
      setResendMsg({
        type: 'error',
        text: err.message || 'Failed to send verification email.'
      })
    } finally {
      setResending(false)
    }
  }

  // Sync browser document title based on pathname
  useEffect(() => {
    const routeMap: Record<string, string> = {
      '/studio/dashboard': 'Dashboard',
      '/studio/galleries': 'Galleries',
      '/studio/packages': 'Packages',
      '/studio/bookings': 'Bookings',
      '/studio/analytics': 'Analytics',
      '/studio/clients': 'Clients',
      '/studio/availability': 'Availability',
      '/studio/settings': 'Settings',
      '/studio/trash': 'Trash',
    }

    let currentTitle = 'Studio'
    if (pathname) {
      if (routeMap[pathname]) {
        currentTitle = routeMap[pathname]
      } else {
        const parts = pathname.split('/')
        if (parts.includes('galleries')) {
          currentTitle = 'Galleries'
          if (parts.includes('new')) {
            currentTitle = 'New Gallery'
          } else if (parts.includes('edit')) {
            currentTitle = 'Edit Gallery'
          }
        }
      }
    }

    document.title = `${currentTitle} | Studio | ifotoset`
  }, [pathname])

  // Escape key listener & background body scroll lock
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
      const handleKeyDown = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
          setIsOpen(false)
        }
      }
      window.addEventListener('keydown', handleKeyDown)
      return () => {
        document.body.style.overflow = ''
        window.removeEventListener('keydown', handleKeyDown)
      }
    }
  }, [isOpen])

  return (
    <div className="flex flex-col md:flex-row min-h-screen bg-background">
      {/* Mobile Top Navigation */}
      <MobileTopBar onMenuClick={() => setIsOpen(true)} logoHref="/studio/dashboard" />

      {/* Controlled Sidebar */}
      <StudioSidebar isOpen={isOpen} setIsOpen={setIsOpen} />

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {currentUser?.user && !currentUser.user.email_verified && (
          <div className="bg-amber-500/10 border-b border-amber-500/20 px-6 py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-sm text-amber-600 dark:text-amber-400">
            <div className="flex items-center gap-2 font-medium">
              <AlertTriangle size={18} className="shrink-0 text-amber-500" />
              <span>Verify your email before accessing features. Check your inbox for the verification link.</span>
            </div>
            <div className="flex items-center gap-4 shrink-0 w-full sm:w-auto justify-between sm:justify-start">
              {resendMsg && (
                <span className={`text-xs font-medium ${
                  resendMsg.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-500'
                }`}>
                  {resendMsg.text}
                </span>
              )}
              <button
                type="button"
                onClick={handleResendVerification}
                disabled={resending || resendCountdown > 0}
                className="flex items-center gap-1.5 px-3 py-1 bg-amber-500 text-white dark:bg-amber-500/20 dark:hover:bg-amber-500/30 hover:bg-amber-600 dark:text-amber-400 border border-amber-500/30 rounded-md font-medium text-xs transition-colors cursor-pointer disabled:opacity-50"
              >
                {resending && <RefreshCw size={12} className="animate-spin" />}
                {resending ? 'Sending...' : resendCountdown > 0 ? `Resend in ${resendCountdown}s` : 'Resend Email'}
              </button>
            </div>
          </div>
        )}
        {children}
      </div>
    </div>
  )
}
