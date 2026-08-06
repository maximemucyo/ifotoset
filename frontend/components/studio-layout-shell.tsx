'use client'

import { useState, useEffect } from 'react'
import { StudioSidebar } from './studio-sidebar'
import { MobileTopBar } from './mobile-top-bar'

interface StudioLayoutShellProps {
  children: React.ReactNode
}

export function StudioLayoutShell({ children }: StudioLayoutShellProps) {
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
      <MobileTopBar onMenuClick={() => setIsOpen(true)} logoHref="/studio/dashboard" />

      {/* Controlled Sidebar */}
      <StudioSidebar isOpen={isOpen} setIsOpen={setIsOpen} />

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {children}
      </div>
    </div>
  )
}
