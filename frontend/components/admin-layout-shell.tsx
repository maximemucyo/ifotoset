'use client'

import { useState, useEffect } from 'react'
import { usePathname } from 'next/navigation'
import { AdminSidebar } from './admin-sidebar'
import { MobileTopBar } from './mobile-top-bar'

interface AdminLayoutShellProps {
  children: React.ReactNode
}

export function AdminLayoutShell({ children }: AdminLayoutShellProps) {
  const [isOpen, setIsOpen] = useState(false)
  const pathname = usePathname()

  // Sync browser document title based on pathname
  useEffect(() => {
    const routeMap: Record<string, string> = {
      '/admin/dashboard': 'Dashboard',
      '/admin/users': 'Users',
      '/admin/galleries': 'Galleries',
      '/admin/payments': 'Payments',
      '/admin/moderation': 'Moderation',
      '/admin/support': 'Support',
      '/admin/analytics': 'Analytics',
      '/admin/settings': 'Settings',
    }

    let currentTitle = 'Admin'
    if (pathname) {
      if (routeMap[pathname]) {
        currentTitle = routeMap[pathname]
      } else {
        const parts = pathname.split('/')
        if (parts.includes('users')) {
          currentTitle = 'Users'
        } else if (parts.includes('galleries')) {
          currentTitle = 'Galleries'
        }
      }
    }

    document.title = `${currentTitle} | Admin | ifotoset`
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
      <MobileTopBar onMenuClick={() => setIsOpen(true)} logoHref="/admin/dashboard" />

      {/* Controlled Sidebar */}
      <AdminSidebar isOpen={isOpen} setIsOpen={setIsOpen} />

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {children}
      </div>
    </div>
  )
}
