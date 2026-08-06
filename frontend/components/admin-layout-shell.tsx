'use client'

import { useState, useEffect } from 'react'
import { AdminSidebar } from './admin-sidebar'
import { MobileTopBar } from './mobile-top-bar'

interface AdminLayoutShellProps {
  children: React.ReactNode
}

export function AdminLayoutShell({ children }: AdminLayoutShellProps) {
  const [isOpen, setIsOpen] = useState(false)

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
